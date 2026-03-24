<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__.'/../models/Olympian.php';
require_once __DIR__.'/../Response.php';
require_once __DIR__.'/../../private/utils.php';

class OlympianController {

    private Olympian $olympianModel;

    public function __construct()
    {
        global $hostname, $database, $username, $password;
        $pdo = connectDatabase($hostname, $database, $username, $password);
        $this->olympianModel = new Olympian($pdo);
    }

    private function extractCategoryFromDiscipline(string $disciplineName): ?string {
    // Ak nazov obsahuje " - ", berieme prvu cast ako kategoriu
    if (strpos($disciplineName, ' - ') !== false) {
        return explode(' - ', $disciplineName)[0];
    }
    // Rucne priradenie pre zname sporty
    if (strpos($disciplineName, 'box') === 0) {
        if (strpos($disciplineName, 'box do') === 0) return 'box';
    }
    if (strpos($disciplineName, 'futbal') === 0) return 'futbal';
    if (strpos($disciplineName, 'ľadový hokej') === 0) return 'hokej';
    if (strpos($disciplineName, 'krasokorčuľovanie') === 0) return 'krasokorčuľovanie';
    if (strpos($disciplineName, 'tenis') === 0) return 'tenis';
    if (strpos($disciplineName, 'vodný slalom') === 0) return 'vodný slalom';
    if (strpos($disciplineName, 'rýchlostná kanoistika') === 0) return 'kanoistika';
    if (strpos($disciplineName, 'dráhová cyklistika') === 0) return 'cyklistika';
    if (strpos($disciplineName, 'športová streľba') === 0) return 'streľba';
    if (strpos($disciplineName, 'atletika') === 0) return 'atletika';
    if (strpos($disciplineName, 'plávanie') === 0) return 'plávanie';
    return null; // ziadna kategoria nebola rozpoznana
}
    private function convertDate(?string $date): ?string {
    if (empty($date)) return null;
    // format YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    $parts = explode('.', trim($date));
    if (count($parts) === 3) {
        $day = str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT);
        $month = str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT);
        $year = (int)$parts[2];
        return sprintf("%04d-%02d-%02d", $year, $month, $day);
    }
    return null; // nepodarilo sa konvertovat
}

    // GET /api/olympians
    public function index()
    {
        $filters = [
            'year'       => $_GET['year'] ?? null,
            'type'       => $_GET['type'] ?? null,
            'discipline' => $_GET['discipline'] ?? null,
            'placing'    => $_GET['placing'] ?? null,
        ];
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        $data = $this->olympianModel->getAllFiltered($filters);
        
         foreach ($data as &$item) {
        if (isset($item['discipline'])) {
            $item['category'] = $this->extractCategoryFromDiscipline($item['discipline']);
        } else {
            $item['category'] = null;
        }
    }

        Response::json($data);
    }

    public function indexAll() 
    {
        $olympians = $this->olympianModel->getAll();
        if (!$olympians) {
            Response::json(["error" => "Olympians not found"], 404);
        }
        Response::json($olympians);
    }
    
    // GET /api/olympians/{id}
    public function show($id)
    {
        $olympian = $this->olympianModel->getByIdWithMedals((int)$id);
        if (!$olympian) {
            Response::json(["error" => "Olympian not found"], 404);
        }
        Response::json($olympian);
    }

    // POST /api/olympians
    public function create()
    {
        $item = json_decode(file_get_contents("php://input"), true);
       

                      $missing = [];
if (empty($item['name'])) $missing[] = 'first_name';
if (empty($item['surname'])) $missing[] = 'last_name';
if (empty($item["birth_country"])) $missing[] = 'birth_country';
if (empty($item["birth_day"])) $missing[] = 'birth_day';
if (empty($item["birth_place"])) $missing[] = 'birth_place';
if (!empty($missing)) {
    Response::json(["error" =>"Missing fields: " . implode(', ', $missing)]);
    return;
}
                
                if (empty($item['oh_year']) || empty($item['oh_type']) || empty($item['discipline']) || empty($item['placing']) || empty($item['oh_city']) ) {
                    Response::json(["error" =>"Medal data incomplete"]);
                    return;
                }

                // Получаем ID (код повторяет create, но можно вынести в отдельный метод)
                $birthCountryId = $this->olympianModel->getOrCreateCountryId($item["birth_country"]);
                $olympicGamesId = $this->olympianModel->getOrCreateOlympicGamesId($item["oh_year"], $item["oh_type"], $item["oh_city"], $item["oh_country"]);
                $disciplineId = $this->olympianModel->getOrCreateDisciplineId($item["discipline"]);
                $medalTypeId = $this->olympianModel->getOrCreateMedalTypeId((int)$item["placing"]);
        

                $athleteData = [
                    "first_name" => $item['name'],
                    "last_name" => $item['surname'],
                    "birth_date" => $this->convertDate($item['birth_day']),
                    "birth_place" => $item['birth_place'],
                    "birth_country_id" => $birthCountryId,
                    "death_date" => $this->convertDate($item['death_day']) ?? null,
                    "death_place" => $item['death_place'] ?? null,
                    "death_country_id" => null,
                ];
                $medalData = [
                    "olympic_games_id" => $olympicGamesId,
                    "discipline_id" => $disciplineId,
                    "medal_type_id" => $medalTypeId,
                ];

    // Спортсмен новый – создаём и его, и награду
     try {

        // Проверяем, существует ли уже такой спортсмен
        $existingAthleteId = $this->olympianModel->findAthlete(
            $athleteData['first_name'],
            $athleteData['last_name'],
            $athleteData['birth_date'],
            $athleteData['birth_country_id']
        );

        if ($existingAthleteId) {
            // Спортсмен есть – проверяем награду
            if ($this->olympianModel->hasMedal($existingAthleteId, $medalData['olympic_games_id'], $medalData['discipline_id'], $medalData['medal_type_id'])) {
                Response::json(["error" =>"Athlete with this medal already exists"]);
            }
            // Добавляем только награду
            $medalId = $this->olympianModel->addMedal($existingAthleteId, $medalData);
            $athleteId = $existingAthleteId;
        } else {
            // Спортсмен новый – создаём и его, и награду
            $athleteId = $this->olympianModel->create($athleteData);
            $medalId = $this->olympianModel->addMedal($athleteId, $medalData);
        }

        Response::json([
            "message" => "Olympian created",
            "id" => $athleteId,
            "medal_id" => $medalId
        ], 201);
    } catch (Exception $e) {
       
            Response::json(["error" => "Database error: " . $e->getMessage()], 500);
        
    }
    }
    // POST /api/olympians/bulk
    public function bulkCreate()
    {
        $items = json_decode(file_get_contents("php://input"), true);
        error_log(print_r($items[0], true));
        if (!is_array($items) || empty($items)) {
            Response::json(["error" => "Invalid or empty data"], 400);
        }

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($items as $index => $item) {
            try {
                // Валидация
                $missing = [];
if (empty($item['name'])) $missing[] = 'first_name';
if (empty($item['surname'])) $missing[] = 'last_name';
if (empty($item["birth_country"])) $missing[] = 'birth_country';
if (empty($item["birth_day"])) $missing[] = 'birth_day';
if (empty($item["birth_place"])) $missing[] = 'birth_place';
if (!empty($missing)) {
    throw new Exception("Missing fields: " . implode(', ', $missing));
}
                
                if (empty($item['oh_year']) || empty($item['oh_type']) || empty($item['discipline']) || empty($item['placing']) || empty($item['oh_city']) ) {
                    throw new Exception("Medal data incomplete");
                }

                // Получаем ID (код повторяет create, но можно вынести в отдельный метод)
                $birthCountryId = $this->olympianModel->getOrCreateCountryId($item["birth_country"]);
                $olympicGamesId = $this->olympianModel->getOrCreateOlympicGamesId($item["oh_year"], $item["oh_type"], $item["oh_city"], $item["oh_country"]);
                $disciplineId = $this->olympianModel->getOrCreateDisciplineId($item["discipline"]);
                $medalTypeId = $this->olympianModel->getOrCreateMedalTypeId((int)$item["placing"]);
        

                $athleteData = [
                    "first_name" => $item['name'],
                    "last_name" => $item['surname'],
                    "birth_date" => $this->convertDate($item['birth_day']),
                    "birth_place" => $item['birth_place'],
                    "birth_country_id" => $birthCountryId,
                    "death_date" => $this->convertDate($item['death_day']) ?? null,
                    "death_place" => $item['death_place'] ?? null,
                    "death_country_id" => null,
                ];
                $medalData = [
                    "olympic_games_id" => $olympicGamesId,
                    "discipline_id" => $disciplineId,
                    "medal_type_id" => $medalTypeId,
                ];

                $athleteId = $this->olympianModel->create($athleteData);
                $medalId = $this->olympianModel->addMedal($athleteId, $medalData);
                $results['success']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Item $index: " . $e->getMessage();
            }
        }

        Response::json($results, $results['failed'] === 0 ? 201 : 207);
    }

    // PUT /api/olympians/{id}
   

    public function update($id)
{
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        Response::json(["error" => "Invalid JSON"], 400);
        return;
    }

    $athleteData = [];
    $medalData = [];

    // Поля спортсмена
    if (isset($data['first_name'])) $athleteData['first_name'] = $data['first_name'];
    if (isset($data['last_name'])) $athleteData['last_name'] = $data['last_name'];
    if (isset($data['birth_date'])) $athleteData['birth_date'] = $data['birth_date'];
    if (isset($data['birth_place'])) $athleteData['birth_place'] = $data['birth_place'];
    if (isset($data['death_date'])) $athleteData['death_date'] = $data['death_date'];
    if (isset($data['death_place'])) $athleteData['death_place'] = $data['death_place'];

    if (isset($data['birth_country'])) {
        $countryId = $this->olympianModel->getCountryIdByName($data['birth_country']);
        if ($countryId) $athleteData['birth_country_id'] = $countryId;
        else Response::json(["error" => "Birth country not found"], 400);
    }
    if (isset($data['death_country'])) {
        $countryId = $this->olympianModel->getCountryIdByName($data['death_country']);
        if ($countryId) $athleteData['death_country_id'] = $countryId;
        else Response::json(["error" => "Death country not found"], 400);
    }

    // Поля медали (обновляем первую медаль спортсмена)
    $hasMedalFields = false;
    $medalUpdate = [];

    if (isset($data['medal'])) {
        $medal = $data['medal'];
        if (isset($medal['year']) && isset($medal['olympic_type']) && isset($medal['city'])) {
            $gamesId = $this->olympianModel->getOrCreateOlympicGamesId($medal['year'], $medal['olympic_type'], $medal['city']);
            if ($gamesId) $medalUpdate['olympic_games_id'] = $gamesId;
        }
        if (isset($medal['sport'])) {
            $sportId = $this->olympianModel->getOrCreateDisciplineId($medal['sport']);
            if ($sportId) $medalUpdate['discipline_id'] = $sportId;
        }
        if (isset($medal['placing'])) {
            $medalTypeId = $this->olympianModel->getOrCreateMedalTypeId((int)$medal['placing']);
            if ($medalTypeId) $medalUpdate['medal_type_id'] = $medalTypeId;
        }
        $hasMedalFields = !empty($medalUpdate);
    }

    $updated = false;

    // Обновление спортсмена
    if (!empty($athleteData)) {
        $updated = $this->olympianModel->updateAthlete((int)$id, $athleteData);
    }

    // Обновление медали (берём первую медаль спортсмена)
    if ($hasMedalFields) {
        $athlete = $this->olympianModel->getById((int)$id);
        if ($athlete) {
            // Находим ID медали (если у спортсмена есть награды)
            $medalId = null;
            $sql = "SELECT id FROM athlete_medals WHERE athlete_id = :id LIMIT 1";
            $stmt = $this->olympianModel->getPdo()->prepare($sql);
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $medalId = $row['id'];

            if ($medalId) {
                $this->olympianModel->updateMedal($medalId, $medalUpdate);
                $updated = true;
            } else {
                // Если медали нет – можно добавить новую
                $this->olympianModel->addMedal($id, $medalUpdate);
                $updated = true;
            }
        }
    }

    if ($updated) {
        Response::json($this->olympianModel->getByIdWithMedals((int)$id));
    } else {
        Response::json(["error" => "No changes or athlete not found"], 404);
    }
}

    // DELETE /api/olympians/{id}
    public function delete($id)
    {
        $deleted = $this->olympianModel->deleteAthlete((int)$id);
        if ($deleted) {
            Response::json(["message" => "Olympian deleted"], 200);
        } else {
            Response::json(["error" => "Failed to delete"], 404);
        }
    }

    public function bulkDelete() {
         try {
            error_log("bulkDelete controller called");
        $deleted = $this->olympianModel->bulkDeleteAthlete();

        if ($deleted) {
            Response::json(["message" => "Olympians deleted"], 200);
        } else {
            Response::json(["error" => "Failed to delete"], 500);
        }
    } catch (Exception $e) {
        Response::json(["error" => $e->getMessage()], 500);
    }
    }


    
}
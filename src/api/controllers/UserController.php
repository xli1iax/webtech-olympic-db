<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__.'/../models/User.php';
require_once __DIR__.'/../Response.php';
require_once __DIR__.'/../../private/utils.php';

class UserController {

    private User $userModel;

    public function __construct()
    {
        global $hostname, $database, $username, $password;
        $pdo = connectDatabase($hostname, $database, $username, $password);
        $this->userModel = new User($pdo);
    }

    public function index()
    {
        $users = $this->userModel->getAll();
        Response::json($users);
    }

    public function show($id)
    {
        $user = $this->userModel->getById((int)$id);

        if (!$user) {
            Response::json(["error" => "User not found"], 404);
        }

        Response::json($user);
    }

    public function create()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (
            !isset($data["first_name"]) ||
            !isset($data["last_name"]) ||
            !isset($data["email"]) ||
            !isset($data["password"])
        ) {
            Response::json(["error" => "Missing required fields"], 400);
        }

        if(isInvalidEmail($data["email"])) {
            Response::json(["error"=>"Invalid email"], 400);
        }

        if(checkPasswordLength($data["password"]) == false){
            Response::json(["error" => "Invalid password"], 400);
        }
        try {
            $id = $this->userModel->create(
                $data["first_name"],
                $data["last_name"],
                $data["email"],
                $data["password"]
            );
        Response::json([
            "status" => 201,
            "data" => [
                "message" => "User created",
                "id" => $id
            ]
        ], 201);

        } catch (PDOException $e) {

            if ($e->getCode() == "23000") {
                Response::json(["error" => "Email already exists"], 409);
            }

            Response::json(["error" => "Database error"], 500);
        }
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::json(["error" => "Invalid JSON"], 400);
        }

        if (!isset($data["first_name"])||!isset($data["last_name"])) {
            Response::json(["error" => "Missing required fields"], 400);
        }

        if (trim($data["first_name"]) === "" || trim($data["last_name"]) === "") {
            Response::json(["error" => "Missing required fields"], 400);
        }
        
        $user = $this->userModel->update($id, $data["first_name"], $data["last_name"]);

        if($user){
            Response::json($this->userModel->getById($id));
   
        }

        Response::json(["error" => "Failed to update"], 404);
    }

    public function delete($id)
    {
        $user = $this->userModel->delete($id);
        if($user){
            Response::json(["message" => "User deleted"], 200);
        }
            
        Response::json(["error" => "Failed to delete"], 404);
    }
}
?>
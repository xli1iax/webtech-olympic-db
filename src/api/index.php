<?php
session_start();

require __DIR__ . "/Router.php";
require __DIR__ . "/controllers/UserController.php";
require __DIR__ . "/controllers/OlympianController.php";

header("Content-Type: application/json");

$router = new Router();
$router->get("/users/me", [UserController::class, "me"]);
$router->get("/users", [UserController::class, "index"]);
$router->get("/users/{id}", [UserController::class, "show"]);
$router->post("/users", [UserController::class, "create"]);
$router->put("/users/{id}", [UserController::class, "update"]);
$router->delete("/users/{id}", [UserController::class, "delete"]);

$router->get("/olympians/bulk", [OlympianController::class, "indexAll"]);
$router->get("/olympians", [OlympianController::class, "index"]);
$router->get("/olympians/{id}", [OlympianController::class, "show"]);
$router->post("/olympians", [OlympianController::class, "create"]);
$router->post("/olympians/bulk", [OlympianController::class, "bulkCreate"]);
$router->put("/olympians/{id}", [OlympianController::class, "update"]);
$router->delete("/olympians/bulk", [OlympianController::class, "bulkDelete"]);
$router->delete("/olympians/{id}", [OlympianController::class, "delete"]);


$router->run();
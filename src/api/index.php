<?php

require __DIR__ . "/Router.php";
require __DIR__ . "/controllers/UserController.php";

header("Content-Type: application/json");

$router = new Router();

$router->get("/users", [UserController::class, "index"]);
$router->get("/users/{id}", [UserController::class, "show"]);
$router->post("/users", [UserController::class, "create"]);
$router->put("/users/{id}", [UserController::class, "update"]);
$router->delete("/users/{id}", [UserController::class, "delete"]);

$router->run();
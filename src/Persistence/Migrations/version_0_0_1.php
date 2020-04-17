<?php


namespace App\Persistence\Migrations;


class version_0_0_1 extends version_0_0_0
{

    private $migrationVersion = 0;


    public function runMigration()
    {
        $queryCreateUserTable = "CREATE TABLE users (
  userid integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  name varchar(45) DEFAULT NULL,
  surname varchar(45) DEFAULT NULL,
  password varchar(45) DEFAULT NULL,
  email varchar(45) DEFAULT NULL,
  role integer DEFAULT 0);";

        $queryCreateLogTable = "CREATE TABLE logs (
  logid integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  content varchar(1000) DEFAULT NULL,
  level integer DEFAULT 0,
  timestamp integer DEFAULT 0);";

        $queryCreateRecoverTable = "CREATE TABLE recover (
  recoverid integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  token varchar(1000) DEFAULT NULL,
  timestamp integer DEFAULT 0,
  userid integer DEFAULT 0);";

        $queryCreateAdminUser = "INSERT INTO users (name, surname, password, email, role) VALUES ('min', 'ad', '$2y$12\$yruYhPoQ829tZ7BizAq4oOZmzPlxeEjoCHxFady.WJBVx.nD7f7ka', 'admin@admin.de', 4)"; // the hashed pw is admin


        $this->database->exec($queryCreateUserTable);
        $this->database->exec($queryCreateAdminUser);

        $this->database->exec($queryCreateLogTable);

        $this->database->exec($queryCreateRecoverTable);

    }

}

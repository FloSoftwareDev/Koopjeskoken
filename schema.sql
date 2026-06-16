CREATE DATABASE IF NOT EXISTS koopjeskoken
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE koopjeskoken;

CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  username   VARCHAR(30)      NOT NULL,
  email      VARCHAR(255)     NOT NULL,
  password   VARCHAR(255)     NOT NULL,
  role       ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(255) NOT NULL,
  description  TEXT,
  ingredients  TEXT,
  instructions TEXT,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_recipes_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

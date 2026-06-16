CREATE DATABASE IF NOT EXISTS koopjeskoken
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE koopjeskoken;

-- ── Users ────────────────────────────────────────────────────────────────────
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

-- ── Recipes (drop old simple version, replace with normalized model) ─────────
DROP TABLE IF EXISTS recipe_supermarkets;
DROP TABLE IF EXISTS recipe_diets;
DROP TABLE IF EXISTS recipe_tags;
DROP TABLE IF EXISTS recipe_steps;
DROP TABLE IF EXISTS recipe_ingredients;
DROP TABLE IF EXISTS recipes;

CREATE TABLE recipes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(64)  NOT NULL,
  name         VARCHAR(255) NOT NULL,
  description  TEXT         NOT NULL,
  gradient     VARCHAR(255) NOT NULL,
  time_minutes SMALLINT     UNSIGNED NOT NULL,
  persons      SMALLINT     UNSIGNED NOT NULL,
  difficulty   ENUM('Makkelijk','Gemiddeld','Moeilijk') NOT NULL,
  price_now    DECIMAL(6,2) NOT NULL,
  price_was    DECIMAL(6,2) NOT NULL,
  total_now    DECIMAL(6,2) NOT NULL,
  total_was    DECIMAL(6,2) NOT NULL,
  saving       DECIMAL(6,2) NOT NULL,
  allergen_gluten       BOOLEAN NOT NULL DEFAULT FALSE,
  allergen_lactose      BOOLEAN NOT NULL DEFAULT FALSE,
  allergen_noten        BOOLEAN NOT NULL DEFAULT FALSE,
  allergen_pinda        BOOLEAN NOT NULL DEFAULT FALSE,
  allergen_ei           BOOLEAN NOT NULL DEFAULT FALSE,
  allergen_soja         BOOLEAN NOT NULL DEFAULT FALSE,
  allergen_vis          BOOLEAN NOT NULL DEFAULT FALSE,
  allergen_schaaldieren BOOLEAN NOT NULL DEFAULT FALSE,
  nutrition_kcal  SMALLINT UNSIGNED NOT NULL,
  nutrition_eiwit SMALLINT UNSIGNED NOT NULL,
  nutrition_koolh SMALLINT UNSIGNED NOT NULL,
  nutrition_vet   SMALLINT UNSIGNED NOT NULL,
  created_by  INT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  KEY ix_price_now (price_now),
  KEY ix_time      (time_minutes),
  CONSTRAINT fk_recipes_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_ingredients (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id     INT UNSIGNED NOT NULL,
  position      SMALLINT UNSIGNED NOT NULL,
  name          VARCHAR(255) NOT NULL,
  per_persoon   DECIMAL(8,2) NOT NULL,
  unit          VARCHAR(32)  NOT NULL,
  inkoop        VARCHAR(255) NOT NULL,
  inkoop_qty    DECIMAL(10,2) NOT NULL,
  prijs_per_pak DECIMAL(6,2) NOT NULL,
  pakken_base   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  deal          BOOLEAN NOT NULL DEFAULT FALSE,
  supermarkt    VARCHAR(32)  NOT NULL,
  PRIMARY KEY (id),
  KEY ix_recipe_ingredients_recipe (recipe_id, position),
  CONSTRAINT fk_ingredients_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_steps (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id INT UNSIGNED NOT NULL,
  position  SMALLINT UNSIGNED NOT NULL,
  text      TEXT NOT NULL,
  PRIMARY KEY (id),
  KEY ix_recipe_steps_recipe (recipe_id, position),
  CONSTRAINT fk_steps_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_tags (
  recipe_id INT UNSIGNED NOT NULL,
  tag       VARCHAR(32)  NOT NULL,
  PRIMARY KEY (recipe_id, tag),
  KEY ix_recipe_tags_tag (tag),
  CONSTRAINT fk_tags_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_diets (
  recipe_id INT UNSIGNED NOT NULL,
  diet      VARCHAR(32)  NOT NULL,
  PRIMARY KEY (recipe_id, diet),
  KEY ix_recipe_diets_diet (diet),
  CONSTRAINT fk_diets_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_supermarkets (
  recipe_id  INT UNSIGNED NOT NULL,
  supermarkt VARCHAR(32)  NOT NULL,
  PRIMARY KEY (recipe_id, supermarkt),
  KEY ix_recipe_supermarkets_sm (supermarkt),
  CONSTRAINT fk_supermarkets_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

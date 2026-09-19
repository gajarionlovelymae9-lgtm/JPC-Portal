-- ============================================================================
-- JPC PORTAL — Database Schema (Phase 3.1-A)
-- ============================================================================
-- Project    : AI-Driven Employee Progress Monitoring Dashboard (John Paul College)
-- Database   : acadtrack_db
-- Engine     : InnoDB
-- Charset    : utf8mb4 / utf8mb4_unicode_ci
--
-- Compatibility: Written for MySQL 8 and MariaDB >= 10.4 (XAMPP local dev).
--   * utf8mb4_unicode_ci works on BOTH engines (avoids the MySQL-only
--     utf8mb4_0900_ai_ci collation so the XAMPP/MariaDB local stack matches).
--
-- Execution: run in dependency order (this file is ordered accordingly).
--
-- NOTE / MIGRATION PATH
--   This is the canonical Phase 3 TARGET design. Existing Phase 2 tables
--   (users, roles, permissions, permission_role, login_activities) are
--   reconciled in Phase 3.1-B via Laravel migrations, NOT by running this
--   file over an existing database. This file is the design reference and the
--   atomic source for the migrations.
--
-- Design decisions in short:
--   * Status/priority/enum-like values use VARCHAR (flexible, app-validated),
--     not rigid MySQL ENUM. Allowed values are documented in
--     docs/database/database-design.md.
--   * JSON is used only where structured-but-flexible data is required
--     (notification payloads, audit diffs, activity metadata, badge criteria
--     snapshots, AI list outputs).
--   * FK delete behaviour: RESTRICT by default to preserve history; CASCADE
--     only on pure link tables where the link is meaningless without the
--     parent (role_user, permission_role).
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `acadtrack_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `acadtrack_db`;

-- ============================================================================
-- GROUP 1 — AUTHENTICATION & RBAC
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 01 users — login accounts only. No employee-specific data.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                BIGINT UNSIGNED            NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(150)               NOT NULL,
  `email`             VARCHAR(190)               NOT NULL,
  `email_verified_at` TIMESTAMP                  NULL DEFAULT NULL,
  `password`          VARCHAR(255)               NOT NULL,
  `status`            VARCHAR(20)                NOT NULL DEFAULT 'active'
                     COMMENT 'active | inactive | suspended',
  `remember_token`    VARCHAR(100)               NULL DEFAULT NULL,
  `last_login_at`     TIMESTAMP                  NULL DEFAULT NULL,
  `created_at`        TIMESTAMP                  NULL DEFAULT NULL,
  `updated_at`        TIMESTAMP                  NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Authentication accounts. No employee profile data here.';

-- ----------------------------------------------------------------------------
-- 02 roles
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(80)     NOT NULL
                 COMMENT 'admin | hr | department_head | employee',
  `display_name` VARCHAR(150)    NOT NULL,
  `description`  TEXT            NULL,
  `created_at`   TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`   TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System roles.';

-- ----------------------------------------------------------------------------
-- 03 permissions
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(120)    NOT NULL
                 COMMENT 'e.g. dashboard.view, reports.approve',
  `display_name` VARCHAR(150)    NOT NULL,
  `description`  TEXT            NULL,
  `created_at`   TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`   TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Granular permission catalog (single source of truth).';

-- ----------------------------------------------------------------------------
-- 04 role_user — users many-to-many roles (link table, safe to CASCADE).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_user` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`    BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP       NULL DEFAULT NULL,
  `updated_at` TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_user` (`role_id`,`user_id`),
  KEY `idx_role_user_user_id` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign`  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `role_user_user_id_foreign`  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Assigns roles to users (replaces Phase 2 single-role column).';

-- ----------------------------------------------------------------------------
-- 05 permission_role — roles many-to-many permissions (link table, safe to CASCADE).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permission_role` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id`       BIGINT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permission_role` (`permission_id`,`role_id`),
  KEY `idx_permission_role_role_id` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign`       FOREIGN KEY (`role_id`)       REFERENCES `roles` (`id`)       ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Grants permissions to roles.';

-- ============================================================================
-- GROUP 2 — ORGANIZATION
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 06 departments
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)     NOT NULL,
  `code`        VARCHAR(20)      NULL,
  `description` TEXT             NULL,
  `status`      VARCHAR(20)      NOT NULL DEFAULT 'active'
                COMMENT 'active | inactive',
  `created_at`  TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_name` (`name`),
  UNIQUE KEY `uq_departments_code` (`code`),
  KEY `idx_departments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Institutional departments. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 07 positions
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `positions` (
  `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)     NOT NULL,
  `code`        VARCHAR(20)      NULL,
  `description` TEXT             NULL,
  `status`      VARCHAR(20)      NOT NULL DEFAULT 'active'
                COMMENT 'active | inactive',
  `created_at`  TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_positions_name` (`name`),
  UNIQUE KEY `uq_positions_code` (`code`),
  KEY `idx_positions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee positions. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 08 employees
-- ----------------------------------------------------------------------------
-- Phase 3.2-B: added employment_type, date_of_birth, gender, address,
-- supervisor_id (self-FK), renamed profile_photo -> profile_photo_path, and
-- indexes (employment_type, supervisor_id, date_hired, name).
CREATE TABLE IF NOT EXISTS `employees` (
  `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`           BIGINT UNSIGNED  NULL
                      COMMENT 'Nullable: historical employees may have no login. UNIQUE: 1:1 with users.',
  `employee_number`   VARCHAR(30)      NOT NULL,
  `first_name`        VARCHAR(100)     NOT NULL,
  `middle_name`       VARCHAR(100)     NULL DEFAULT NULL,
  `last_name`         VARCHAR(100)     NOT NULL,
  `suffix`            VARCHAR(20)      NULL DEFAULT NULL,
  `position_id`       BIGINT UNSIGNED  NULL,
  `department_id`     BIGINT UNSIGNED  NOT NULL,
  `employment_status` VARCHAR(20)      NOT NULL DEFAULT 'active'
                      COMMENT 'active | inactive | on_leave | suspended | resigned | retired | terminated',
  `employment_type`   VARCHAR(20)      NOT NULL DEFAULT 'regular'
                      COMMENT 'regular | probationary | part_time | contractual | temporary',
  `supervisor_id`     BIGINT UNSIGNED  NULL
                      COMMENT 'Self-referencing line-manager (not department-head role).',
  `date_of_birth`     DATE             NULL DEFAULT NULL,
  `gender`            VARCHAR(30)      NULL DEFAULT NULL
                      COMMENT 'female | male | prefer_not_to_say',
  `date_hired`        DATE             NULL DEFAULT NULL,
  `date_ended`        DATE             NULL DEFAULT NULL,
  `phone`             VARCHAR(30)      NULL DEFAULT NULL,
  `address`           TEXT             NULL,
  `profile_photo_path`VARCHAR(500)     NULL DEFAULT NULL
                      COMMENT 'Storage-relative path under storage/app/public/employees/. No binary in MySQL.',
  `created_at`        TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`        TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`        TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employees_user_id`         (`user_id`),
  UNIQUE KEY `uq_employees_employee_number` (`employee_number`),
  KEY `idx_employees_department_id`     (`department_id`),
  KEY `idx_employees_position_id`       (`position_id`),
  KEY `idx_employees_employment_status` (`employment_status`),
  KEY `idx_employees_employment_type`   (`employment_type`),
  KEY `idx_employees_supervisor_id`     (`supervisor_id`),
  KEY `idx_employees_date_hired`        (`date_hired`),
  KEY `idx_employees_name`              (`last_name`,`first_name`),
  CONSTRAINT `employees_user_id_foreign`       FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`)       ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employees_position_id_foreign`   FOREIGN KEY (`position_id`)   REFERENCES `positions` (`id`)   ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employees_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`id`)   ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee profiles. One user-account per employee (unique). Supervisor is a self-FK. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 09 department_heads — historical leadership assignments.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `department_heads` (
  `id`                      BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `department_id`           BIGINT UNSIGNED  NOT NULL,
  `employee_id`             BIGINT UNSIGNED  NOT NULL,
  `start_date`              DATE             NOT NULL,
  `end_date`                DATE             NULL DEFAULT NULL
                            COMMENT 'NULL while still current head.',
  `is_current`              TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at`              TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`              TIMESTAMP        NULL DEFAULT NULL,
  `current_department_id`   BIGINT UNSIGNED GENERATED ALWAYS AS
                            (IF(`is_current`, `department_id`, NULL)) STORED
                            COMMENT 'Derived: enforces one CURRENT head per department.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_department_heads_current` (`current_department_id`),
  KEY `idx_department_heads_department_id` (`department_id`),
  KEY `idx_department_heads_employee_id`   (`employee_id`),
  KEY `idx_department_heads_is_current`    (`is_current`),
  CONSTRAINT `department_heads_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `department_heads_employee_id_foreign`   FOREIGN KEY (`employee_id`)   REFERENCES `employees` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historical department-head leadership. Generated column keeps at most one current head per department.';

-- ----------------------------------------------------------------------------
-- 08b employee_histories — append-only change log for employee profile fields
--     (Phase 3.2-B). Generic: field_name + old/new value captures department,
--     position, employment_status, employment_type, supervisor, dates, contact.
--     NOT soft-deletable (history must survive).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_histories` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`   BIGINT UNSIGNED NOT NULL,
  `field_name`    VARCHAR(100)    NOT NULL
                  COMMENT 'department | position | employment_status | employment_type | supervisor | date_hired | date_ended | date_of_birth | gender | phone | address | profile_photo_path',
  `old_value`     TEXT            NULL,
  `new_value`     TEXT            NULL,
  `effective_date`DATE            NOT NULL,
  `reason`        TEXT            NULL,
  `changed_by`    BIGINT UNSIGNED NULL
                  COMMENT 'users.id of the actor; NULL for bulk/system events. SET NULL if the user is removed.',
  `created_at`    TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_histories_employee_id`     (`employee_id`),
  KEY `idx_employee_histories_field_name`      (`field_name`),
  KEY `idx_employee_histories_effective_date`  (`effective_date`),
  KEY `idx_employee_histories_employee_field`  (`employee_id`,`field_name`),
  CONSTRAINT `employee_histories_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_histories_changed_by_foreign`  FOREIGN KEY (`changed_by`)  REFERENCES `users`      (`id`) ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Append-only employee attribute change history. Never soft-deleted; RESTRICT protects records.';

-- ============================================================================
-- GROUP 3 — ACADEMIC YEAR & PLANNING
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 10 academic_years
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `academic_years` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(20)     NOT NULL
               COMMENT 'e.g. 2026-2027',
  `start_date` DATE            NOT NULL,
  `end_date`   DATE            NOT NULL,
  `status`     VARCHAR(20)     NOT NULL DEFAULT 'draft'
               COMMENT 'draft | active | closed',
  `created_at` TIMESTAMP       NULL DEFAULT NULL,
  `updated_at` TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_years_name` (`name`),
  KEY `idx_academic_years_status`      (`status`),
  KEY `idx_academic_years_start_date`  (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Planning periods. Single-active-year is enforced at application level (spec allows exceptions).';

-- ----------------------------------------------------------------------------
-- 11 annual_plans
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `annual_plans` (
  `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `academic_year_id`BIGINT UNSIGNED  NOT NULL,
  `department_id`   BIGINT UNSIGNED  NOT NULL,
  `title`           VARCHAR(200)     NOT NULL,
  `description`     TEXT             NULL,
  `status`          VARCHAR(20)      NOT NULL DEFAULT 'draft'
                    COMMENT 'draft | submitted | active | completed | cancelled',
  `created_by`      BIGINT UNSIGNED  NULL,
  `approved_by`     BIGINT UNSIGNED  NULL,
  `approved_at`     TIMESTAMP        NULL DEFAULT NULL,
  `created_at`      TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`      TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_annual_plans_academic_year_id` (`academic_year_id`),
  KEY `idx_annual_plans_department_id`    (`department_id`),
  KEY `idx_annual_plans_status`           (`status`),
  KEY `idx_annual_plans_created_by`       (`created_by`),
  KEY `idx_annual_plans_approved_by`      (`approved_by`),
  CONSTRAINT `annual_plans_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `annual_plans_department_id_foreign`    FOREIGN KEY (`department_id`)    REFERENCES `departments`   (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `annual_plans_created_by_foreign`       FOREIGN KEY (`created_by`)       REFERENCES `users`         (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `annual_plans_approved_by_foreign`      FOREIGN KEY (`approved_by`)      REFERENCES `users`         (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Department/institutional annual plans. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 12 employee_targets
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_targets` (
  `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `annual_plan_id`  BIGINT UNSIGNED  NOT NULL,
  `employee_id`     BIGINT UNSIGNED  NOT NULL,
  `title`           VARCHAR(200)     NOT NULL,
  `description`     TEXT             NULL,
  `target_value`    DECIMAL(12,2)    NULL DEFAULT NULL,
  `target_unit`     VARCHAR(50)      NULL DEFAULT NULL
                    COMMENT 'e.g. tasks, reports',
  `start_date`      DATE             NULL DEFAULT NULL,
  `end_date`        DATE             NULL DEFAULT NULL,
  `status`          VARCHAR(20)      NOT NULL DEFAULT 'draft'
                    COMMENT 'draft | active | completed | cancelled',
  `created_at`      TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`      TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_targets_annual_plan_id` (`annual_plan_id`),
  KEY `idx_employee_targets_employee_id`    (`employee_id`),
  KEY `idx_employee_targets_status`         (`status`),
  KEY `idx_employee_targets_employee_status`(`employee_id`,`status`),
  CONSTRAINT `employee_targets_annual_plan_id_foreign` FOREIGN KEY (`annual_plan_id`) REFERENCES `annual_plans`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_targets_employee_id_foreign`    FOREIGN KEY (`employee_id`)    REFERENCES `employees`        (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee-specific yearly targets. Soft-deleted.';

-- ============================================================================
-- GROUP 4 — TASK MANAGEMENT
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 13 tasks
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `annual_plan_id` BIGINT UNSIGNED  NULL
                   COMMENT 'Nullable for ad-hoc tasks.',
  `department_id`  BIGINT UNSIGNED  NULL
                   COMMENT 'Nullable for personal/ad-hoc tasks.',
  `created_by`     BIGINT UNSIGNED  NULL,
  `title`          VARCHAR(200)     NOT NULL,
  `description`    TEXT             NULL,
  `priority`       VARCHAR(10)      NOT NULL DEFAULT 'medium'
                   COMMENT 'low | medium | high | urgent',
  `start_date`     DATE             NULL DEFAULT NULL,
  `due_date`       DATE             NULL DEFAULT NULL,
  `target_value`   DECIMAL(12,2)    NULL DEFAULT NULL,
  `target_unit`    VARCHAR(50)      NULL DEFAULT NULL,
  `required_output`TEXT             NULL,
  `status`         VARCHAR(30)      NOT NULL DEFAULT 'not_started'
                   COMMENT 'not_started | in_progress | submitted | under_review | revision_required | approved | completed | overdue | cancelled',
  `completed_at`   TIMESTAMP        NULL DEFAULT NULL,
  `created_at`     TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`     TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_annual_plan_id` (`annual_plan_id`),
  KEY `idx_tasks_department_id`  (`department_id`),
  KEY `idx_tasks_created_by`     (`created_by`),
  KEY `idx_tasks_status`         (`status`),
  KEY `idx_tasks_due_date`       (`due_date`),
  KEY `idx_tasks_priority`       (`priority`),
  KEY `idx_tasks_department_status` (`department_id`,`status`),
  CONSTRAINT `tasks_annual_plan_id_foreign` FOREIGN KEY (`annual_plan_id`) REFERENCES `annual_plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `tasks_department_id_foreign`  FOREIGN KEY (`department_id`)  REFERENCES `departments`  (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `tasks_created_by_foreign`     FOREIGN KEY (`created_by`)     REFERENCES `users`        (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tasks. Status is lifecycle-driven. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 14 task_assignments — one task → many employees, INDEPENDENT progress.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `task_assignments` (
  `id`                 BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `task_id`            BIGINT UNSIGNED  NOT NULL,
  `employee_id`        BIGINT UNSIGNED  NOT NULL,
  `assigned_by`        BIGINT UNSIGNED  NULL,
  `assigned_at`        TIMESTAMP        NULL DEFAULT CURRENT_TIMESTAMP,
  `start_date`         DATE             NULL DEFAULT NULL,
  `due_date`           DATE             NULL DEFAULT NULL
                       COMMENT 'Assignment-level deadline; may differ from task due_date.',
  `progress_percentage`DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
  `status`             VARCHAR(30)      NOT NULL DEFAULT 'not_started'
                       COMMENT 'not_started | in_progress | submitted | under_review | revision_required | approved | completed | overdue | cancelled',
  `completed_at`       TIMESTAMP        NULL DEFAULT NULL,
  `created_at`         TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_assignments` (`task_id`,`employee_id`)
         COMMENT 'Guarantees one assignment per employee per task.',
  KEY `idx_task_assignments_employee_id` (`employee_id`),
  KEY `idx_task_assignments_status`      (`status`),
  KEY `idx_task_assignments_assigned_by` (`assigned_by`),
  KEY `idx_task_assignments_due_date`    (`due_date`),
  CONSTRAINT `task_assignments_task_id_foreign`     FOREIGN KEY (`task_id`)     REFERENCES `tasks`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-employee task assignments with independent progress.';

-- ----------------------------------------------------------------------------
-- 15 task_references
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `task_references` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `task_id`        BIGINT UNSIGNED  NOT NULL,
  `title`          VARCHAR(200)     NOT NULL,
  `description`    TEXT             NULL,
  `reference_type` VARCHAR(20)      NOT NULL DEFAULT 'document'
                   COMMENT 'policy | guideline | document | website | manual | other',
  `reference_url`  VARCHAR(500)     NULL DEFAULT NULL,
  `file_path`      VARCHAR(500)     NULL DEFAULT NULL,
  `created_at`     TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`     TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_references_task_id`       (`task_id`),
  KEY `idx_task_references_reference_type`(`reference_type`),
  CONSTRAINT `task_references_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='References for tasks; later used for AI reference validation. Soft-deleted.';

-- ============================================================================
-- GROUP 5 — REPORT MANAGEMENT
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 16 reports
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
  `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `task_id`         BIGINT UNSIGNED  NOT NULL,
  `employee_id`     BIGINT UNSIGNED  NOT NULL,
  `title`           VARCHAR(200)     NOT NULL,
  `description`     TEXT             NULL,
  `submission_date` DATE             NULL DEFAULT NULL,
  `deadline`        DATE             NULL DEFAULT NULL,
  `status`          VARCHAR(30)      NOT NULL DEFAULT 'draft'
                    COMMENT 'draft | submitted | processing | ai_validated | under_manual_review | revision_required | approved | rejected',
  `is_late`         TINYINT(1)       NOT NULL DEFAULT 0
                    COMMENT 'Derived (submission_date > deadline); maintained by the application.',
  `version`         INT UNSIGNED     NOT NULL DEFAULT 1
                    COMMENT 'Current/latest version (history preserved in report_versions).',
  `submitted_at`    TIMESTAMP        NULL DEFAULT NULL,
  `approved_at`     TIMESTAMP        NULL DEFAULT NULL,
  `rejected_at`     TIMESTAMP        NULL DEFAULT NULL,
  `created_at`      TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`      TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reports_task_id`        (`task_id`),
  KEY `idx_reports_employee_id`    (`employee_id`),
  KEY `idx_reports_status`         (`status`),
  KEY `idx_reports_submitted_at`   (`submitted_at`),
  KEY `idx_reports_is_late`        (`is_late`),
  KEY `idx_reports_deadline`       (`deadline`),
  KEY `idx_reports_employee_status`(`employee_id`,`status`),
  CONSTRAINT `reports_task_id_foreign`    FOREIGN KEY (`task_id`)    REFERENCES `tasks`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `reports_employee_id_foreign`FOREIGN KEY (`employee_id`)REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee reports. Historical versions are never overwritten. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 17 report_versions — preserves revision history (v1, v2, ...).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_versions` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `report_id`      BIGINT UNSIGNED  NOT NULL,
  `version_number` INT UNSIGNED     NOT NULL,
  `submitted_by`   BIGINT UNSIGNED  NULL,
  `file_path`      VARCHAR(500)     NULL DEFAULT NULL,
  `notes`          TEXT             NULL,
  `submitted_at`   TIMESTAMP        NULL DEFAULT NULL,
  `created_at`     TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_versions` (`report_id`,`version_number`),
  KEY `idx_report_versions_submitted_by` (`submitted_by`),
  CONSTRAINT `report_versions_report_id_foreign`   FOREIGN KEY (`report_id`)   REFERENCES `reports` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `report_versions_submitted_by_foreign`FOREIGN KEY (`submitted_by`)REFERENCES `users`   (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Report revision history. Cannot have two identical version numbers per report.';

-- ----------------------------------------------------------------------------
-- 18 report_files — metadata only; binaries live in secure storage.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_files` (
  `id`                 BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `report_id`          BIGINT UNSIGNED   NOT NULL,
  `report_version_id`  BIGINT UNSIGNED   NOT NULL,
  `original_filename`  VARCHAR(255)      NOT NULL,
  `stored_filename`    VARCHAR(255)      NOT NULL,
  `storage_disk`       VARCHAR(50)       NOT NULL DEFAULT 'public',
  `file_path`          VARCHAR(500)      NOT NULL,
  `mime_type`          VARCHAR(120)      NULL DEFAULT NULL,
  `file_size`          BIGINT UNSIGNED   NULL DEFAULT 0
                       COMMENT 'Size in bytes.',
  `checksum`           CHAR(64)          NULL DEFAULT NULL
                       COMMENT 'SHA-256 of the stored file (integrity / OCR de-dup).',
  `uploaded_by`        BIGINT UNSIGNED   NULL,
  `created_at`         TIMESTAMP         NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP         NULL DEFAULT NULL,
  `deleted_at`         TIMESTAMP         NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_files_report_id`        (`report_id`),
  KEY `idx_report_files_report_version_id`(`report_version_id`),
  KEY `idx_report_files_uploaded_by`      (`uploaded_by`),
  CONSTRAINT `report_files_report_id_foreign`       FOREIGN KEY (`report_id`)        REFERENCES `reports` (`id`)        ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `report_files_report_version_id_foreign` FOREIGN KEY (`report_version_id`) REFERENCES `report_versions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `report_files_uploaded_by_foreign`      FOREIGN KEY (`uploaded_by`)     REFERENCES `users`   (`id`)        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='File metadata. Actual binaries are stored on disk/object storage, never in MySQL. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 19 report_references — references anchored to a submitted report.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_references` (
  `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `report_id`         BIGINT UNSIGNED  NOT NULL,
  `task_reference_id` BIGINT UNSIGNED  NULL
                      COMMENT 'Optional link to the originating task reference.',
  `reference_text`    TEXT             NULL,
  `reference_url`     VARCHAR(500)     NULL DEFAULT NULL,
  `created_at`        TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`        TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_references_report_id`         (`report_id`),
  KEY `idx_report_references_task_reference_id` (`task_reference_id`),
  CONSTRAINT `report_references_report_id_foreign`         FOREIGN KEY (`report_id`)         REFERENCES `reports`          (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `report_references_task_reference_id_foreign` FOREIGN KEY (`task_reference_id`) REFERENCES `task_references` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Anchors reports to required references for AI alignment checks.';

-- ============================================================================
-- GROUP 6 — OCR
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 20 ocr_processings
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ocr_processings` (
  `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `report_file_id`   BIGINT UNSIGNED   NOT NULL,
  `provider`         VARCHAR(100)      NOT NULL
                     COMMENT 'e.g. google_vision',
  `status`           VARCHAR(20)       NOT NULL DEFAULT 'pending'
                     COMMENT 'pending | processing | completed | failed',
  `language`         VARCHAR(20)       NULL DEFAULT NULL,
  `raw_text`         LONGTEXT          NULL
                     COMMENT 'Extracted text; NEVER overwrites the original file.',
  `confidence_score` DECIMAL(5,4)      NULL DEFAULT NULL
                     COMMENT '0.0000 - 1.0000.',
  `started_at`       TIMESTAMP         NULL DEFAULT NULL,
  `completed_at`     TIMESTAMP         NULL DEFAULT NULL,
  `error_message`    TEXT              NULL,
  `created_at`       TIMESTAMP         NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP         NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ocr_processings_report_file_id` (`report_file_id`),
  KEY `idx_ocr_processings_status`         (`status`),
  CONSTRAINT `ocr_processings_report_file_id_foreign` FOREIGN KEY (`report_file_id`) REFERENCES `report_files` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='OCR jobs. Extracted text is derived data; original file is authoritative.';

-- ============================================================================
-- GROUP 7 — AI PROCESSING
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 21 ai_analyses — execution log per analysis type.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_analyses` (
  `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `report_id`     BIGINT UNSIGNED  NOT NULL,
  `analysis_type` VARCHAR(40)      NOT NULL
                  COMMENT 'summarization | task_alignment | reference_alignment | quality_analysis | similarity_detection | employee_data_validation | performance_analysis',
  `provider`      VARCHAR(100)     NOT NULL,
  `model`         VARCHAR(100)     NULL DEFAULT NULL,
  `status`        VARCHAR(20)      NOT NULL DEFAULT 'pending'
                  COMMENT 'pending | processing | completed | failed',
  `input_hash`    CHAR(64)         NULL DEFAULT NULL
                  COMMENT 'SHA-256 of the analysed input (dedup/caching).',
  `started_at`    TIMESTAMP        NULL DEFAULT NULL,
  `completed_at`  TIMESTAMP        NULL DEFAULT NULL,
  `error_message` TEXT             NULL,
  `created_at`    TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_analyses_report_id`   (`report_id`),
  KEY `idx_ai_analyses_analysis_type`(`analysis_type`),
  KEY `idx_ai_analyses_status`      (`status`),
  CONSTRAINT `ai_analyses_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI execution log. Provider/model traced for auditability.';

-- ----------------------------------------------------------------------------
-- 22 ai_summaries — AI-authored summaries (kept separate from human content).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_summaries` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`          BIGINT UNSIGNED NOT NULL,
  `ai_analysis_id`     BIGINT UNSIGNED NOT NULL,
  `summary`            LONGTEXT        NOT NULL,
  `key_accomplishments`JSON            NULL,
  `key_findings`       JSON            NULL,
  `issues`             JSON            NULL,
  `recommendations`    JSON            NULL,
  `action_items`       JSON            NULL,
  `generated_at`       TIMESTAMP       NULL DEFAULT NULL,
  `created_at`         TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_summaries_report_id`      (`report_id`),
  KEY `idx_ai_summaries_ai_analysis_id` (`ai_analysis_id`),
  CONSTRAINT `ai_summaries_report_id_foreign`      FOREIGN KEY (`report_id`)      REFERENCES `reports`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ai_summaries_ai_analysis_id_foreign` FOREIGN KEY (`ai_analysis_id`) REFERENCES `ai_analyses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI-generated summaries. Marked as machine output, reviewable by humans.';

-- ----------------------------------------------------------------------------
-- 23 ai_validation_results — AI scores + an ADVISORY result.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_validation_results` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`                BIGINT UNSIGNED NOT NULL,
  `ai_analysis_id`           BIGINT UNSIGNED NOT NULL,
  `task_alignment_score`     DECIMAL(5,4)    NULL DEFAULT NULL,
  `reference_alignment_score`DECIMAL(5,4)    NULL DEFAULT NULL,
  `completeness_score`       DECIMAL(5,4)    NULL DEFAULT NULL,
  `relevance_score`          DECIMAL(5,4)    NULL DEFAULT NULL,
  `evidence_score`           DECIMAL(5,4)    NULL DEFAULT NULL,
  `clarity_score`            DECIMAL(5,4)    NULL DEFAULT NULL,
  `overall_score`            DECIMAL(5,4)    NULL DEFAULT NULL,
  `result`                   VARCHAR(20)     NULL DEFAULT NULL
                             COMMENT 'pass | warning | fail (advisory only).',
  `explanation`              TEXT            NULL,
  `created_at`               TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`               TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_validation_results_report_id`      (`report_id`),
  KEY `idx_ai_validation_results_ai_analysis_id` (`ai_analysis_id`),
  KEY `idx_ai_validation_results_result`         (`result`),
  CONSTRAINT `ai_validation_results_report_id_foreign`      FOREIGN KEY (`report_id`)      REFERENCES `reports`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ai_validation_results_ai_analysis_id_foreign` FOREIGN KEY (`ai_analysis_id`) REFERENCES `ai_analyses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI validation is ADVISORY. It never overrides human review/approval.';

-- ----------------------------------------------------------------------------
-- 24 ai_validation_items — granular AI findings.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_validation_items` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ai_validation_result_id`  BIGINT UNSIGNED NOT NULL,
  `category`                 VARCHAR(40)     NOT NULL
                             COMMENT 'task_alignment | reference_alignment | completeness | relevance | evidence | clarity',
  `severity`                 VARCHAR(20)     NOT NULL DEFAULT 'info'
                             COMMENT 'info | warning | critical',
  `finding`                  TEXT            NULL,
  `evidence`                 TEXT            NULL,
  `recommendation`           TEXT            NULL,
  `created_at`               TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`               TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_validation_items_result_id` (`ai_validation_result_id`),
  KEY `idx_ai_validation_items_category` (`category`),
  CONSTRAINT `ai_validation_items_ai_validation_result_id_foreign` FOREIGN KEY (`ai_validation_result_id`) REFERENCES `ai_validation_results` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detailed findings behind an AI validation result.';

-- ----------------------------------------------------------------------------
-- 25 ai_similarity_results — similarity flags NEVER auto-accuse plagiarism.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_similarity_results` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`          BIGINT UNSIGNED NOT NULL,
  `compared_report_id` BIGINT UNSIGNED NOT NULL,
  `similarity_score`   DECIMAL(5,4)    NOT NULL DEFAULT 0.0000
                       COMMENT '0.0000 - 1.0000.',
  `status`             VARCHAR(30)     NOT NULL DEFAULT 'normal'
                       COMMENT 'normal | potential_similarity | high_similarity | reviewed',
  `explanation`        TEXT            NULL,
  `created_at`         TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_similarity_results` (`report_id`,`compared_report_id`),
  KEY `idx_ai_similarity_results_compared_report_id` (`compared_report_id`),
  CONSTRAINT `ai_similarity_results_report_id_foreign`          FOREIGN KEY (`report_id`)          REFERENCES `reports` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ai_similarity_results_compared_report_id_foreign` FOREIGN KEY (`compared_report_id`) REFERENCES `reports` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Report similarity comparisons. A status is a FLAG FOR REVIEW, not a verdict.';

-- ============================================================================
-- GROUP 8 — MANUAL REVIEW & APPROVAL
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 26 report_reviews — human review actions.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_reviews` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`   BIGINT UNSIGNED NOT NULL,
  `reviewer_id` BIGINT UNSIGNED NULL,
  `review_type` VARCHAR(20)     NOT NULL DEFAULT 'initial'
                COMMENT 'initial | revision | final',
  `decision`    VARCHAR(30)     NOT NULL
                COMMENT 'approved | rejected | revision_required',
  `comments`    TEXT            NULL,
  `reviewed_at` TIMESTAMP       NULL DEFAULT NULL,
  `created_at`  TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_reviews_report_id`   (`report_id`),
  KEY `idx_report_reviews_reviewer_id` (`reviewer_id`),
  KEY `idx_report_reviews_review_type` (`review_type`),
  CONSTRAINT `report_reviews_report_id_foreign`   FOREIGN KEY (`report_id`)   REFERENCES `reports` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `report_reviews_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users`   (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Manual review trail. History is never deleted.';

-- ----------------------------------------------------------------------------
-- 27 report_approvals — formal approval/rejection history.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_approvals` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`   BIGINT UNSIGNED NOT NULL,
  `approver_id` BIGINT UNSIGNED NULL,
  `decision`    VARCHAR(20)     NOT NULL
                COMMENT 'approved | rejected',
  `comments`    TEXT            NULL,
  `approved_at` TIMESTAMP       NULL DEFAULT NULL,
  `created_at`  TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_approvals_report_id`   (`report_id`),
  KEY `idx_report_approvals_approver_id` (`approver_id`),
  CONSTRAINT `report_approvals_report_id_foreign`   FOREIGN KEY (`report_id`)   REFERENCES `reports` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `report_approvals_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users`   (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Formal approval history. Never deleted.';

-- ============================================================================
-- GROUP 9 — PERFORMANCE
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 28 employee_progress_snapshots — point-in-time trend data.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_progress_snapshots` (
  `id`                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employee_id`           BIGINT UNSIGNED  NOT NULL,
  `academic_year_id`      BIGINT UNSIGNED  NOT NULL,
  `snapshot_date`         DATE             NOT NULL,
  `total_tasks`           INT UNSIGNED     NOT NULL DEFAULT 0,
  `completed_tasks`       INT UNSIGNED     NOT NULL DEFAULT 0,
  `pending_tasks`         INT UNSIGNED     NOT NULL DEFAULT 0,
  `overdue_tasks`         INT UNSIGNED     NOT NULL DEFAULT 0,
  `total_reports`         INT UNSIGNED     NOT NULL DEFAULT 0,
  `submitted_reports`     INT UNSIGNED     NOT NULL DEFAULT 0,
  `approved_reports`      INT UNSIGNED     NOT NULL DEFAULT 0,
  `late_reports`          INT UNSIGNED     NOT NULL DEFAULT 0,
  `completion_rate`       DECIMAL(5,2)     NULL DEFAULT NULL
                          COMMENT 'Percentage 0.00 - 100.00.',
  `on_time_rate`          DECIMAL(5,2)     NULL DEFAULT NULL,
  `approval_rate`         DECIMAL(5,2)     NULL DEFAULT NULL,
  `target_achievement_rate` DECIMAL(5,2)   NULL DEFAULT NULL,
  `overall_score`         DECIMAL(5,2)     NULL DEFAULT NULL,
  `created_at`            TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`            TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_progress_snapshots_employee_id`      (`employee_id`),
  KEY `idx_employee_progress_snapshots_academic_year_id` (`academic_year_id`),
  KEY `idx_employee_progress_snapshots_snapshot_date`    (`snapshot_date`),
  KEY `idx_employee_progress_snapshots_employee_date`    (`employee_id`,`snapshot_date`),
  CONSTRAINT `employee_progress_snapshots_employee_id_foreign`      FOREIGN KEY (`employee_id`)      REFERENCES `employees`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_progress_snapshots_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historical snapshots for trend analysis. Never recalculated in place.';

-- ----------------------------------------------------------------------------
-- 29 employee_performance_records — calculated results per period.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_performance_records` (
  `id`                      BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employee_id`             BIGINT UNSIGNED  NOT NULL,
  `academic_year_id`        BIGINT UNSIGNED  NOT NULL,
  `period_type`             VARCHAR(20)      NOT NULL DEFAULT 'monthly'
                            COMMENT 'monthly | quarterly | yearly',
  `period_start`            DATE             NOT NULL,
  `period_end`              DATE             NOT NULL,
  `completion_rate`         DECIMAL(5,2)     NULL DEFAULT NULL,
  `on_time_rate`            DECIMAL(5,2)     NULL DEFAULT NULL,
  `approval_rate`           DECIMAL(5,2)     NULL DEFAULT NULL,
  `quality_score`           DECIMAL(5,2)     NULL DEFAULT NULL,
  `target_achievement_rate` DECIMAL(5,2)     NULL DEFAULT NULL,
  `overall_score`           DECIMAL(5,2)     NULL DEFAULT NULL,
  `performance_status`      VARCHAR(20)      NOT NULL DEFAULT 'consistent'
                            COMMENT 'improving | consistent | declining | at_risk',
  `created_at`              TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`              TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_performance_records_period`
         (`employee_id`,`academic_year_id`,`period_type`,`period_start`,`period_end`)
         COMMENT 'One record per employee per period window.',
  KEY `idx_employee_performance_records_academic_year_id` (`academic_year_id`),
  KEY `idx_employee_performance_records_period_start`     (`period_start`),
  CONSTRAINT `employee_performance_records_employee_id_foreign`      FOREIGN KEY (`employee_id`)      REFERENCES `employees`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_performance_records_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Calculated performance records. Recomputing updates their OWN row; snapshots stay frozen.';

-- ============================================================================
-- GROUP 10 — ACHIEVEMENTS & BADGES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 30 badges — badge catalog.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `badges` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150)     NOT NULL,
  `code`           VARCHAR(50)      NOT NULL
                   COMMENT 'e.g. employee_of_month, 100_completion',
  `description`    TEXT             NULL,
  `icon`           VARCHAR(100)     NULL DEFAULT NULL,
  `criteria_type`  VARCHAR(40)      NULL DEFAULT NULL
                   COMMENT 'e.g. completion_rate, on_time_rate, overall_score',
  `criteria_value` JSON             NULL
                   COMMENT 'Flexible threshold definition, e.g. {"threshold":90,"period":"monthly"}',
  `is_active`      TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`     TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_badges_code` (`code`),
  UNIQUE KEY `uq_badges_name` (`name`),
  KEY `idx_badges_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Achievement badge catalog. Soft-deleted.';

-- ----------------------------------------------------------------------------
-- 31 employee_badges — awarded badges (historical, never deleted).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_badges` (
  `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employee_id`      BIGINT UNSIGNED  NOT NULL,
  `badge_id`         BIGINT UNSIGNED  NOT NULL,
  `academic_year_id` BIGINT UNSIGNED  NOT NULL,
  `period_start`     DATE             NULL DEFAULT NULL,
  `period_end`       DATE             NULL DEFAULT NULL,
  `awarded_at`       TIMESTAMP        NULL DEFAULT NULL,
  `awarded_by`       BIGINT UNSIGNED  NULL,
  `criteria_snapshot`JSON             NULL
                      COMMENT 'Criteria values at the moment of award.',
  `created_at`       TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_badges_award`
         (`employee_id`,`badge_id`,`academic_year_id`,`period_start`,`period_end`),
  KEY `idx_employee_badges_badge_id`         (`badge_id`),
  KEY `idx_employee_badges_academic_year_id` (`academic_year_id`),
  KEY `idx_employee_badges_awarded_by`       (`awarded_by`),
  CONSTRAINT `employee_badges_employee_id_foreign`      FOREIGN KEY (`employee_id`)      REFERENCES `employees`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_badges_badge_id_foreign`         FOREIGN KEY (`badge_id`)         REFERENCES `badges`        (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_badges_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_badges_awarded_by_foreign`       FOREIGN KEY (`awarded_by`)       REFERENCES `users`         (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Badges awarded to employees. Achievements are historical and never deleted.';

-- ----------------------------------------------------------------------------
-- 32 monthly_achievements
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monthly_achievements` (
  `id`                 BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employee_id`        BIGINT UNSIGNED  NOT NULL,
  `academic_year_id`   BIGINT UNSIGNED  NOT NULL,
  `month`              TINYINT UNSIGNED NOT NULL
                       COMMENT '1 - 12.',
  `year`               SMALLINT UNSIGNED NOT NULL,
  `completion_rate`    DECIMAL(5,2)     NULL DEFAULT NULL,
  `on_time_rate`       DECIMAL(5,2)     NULL DEFAULT NULL,
  `approved_reports`   INT UNSIGNED     NOT NULL DEFAULT 0,
  `completed_tasks`    INT UNSIGNED     NOT NULL DEFAULT 0,
  `overall_score`      DECIMAL(5,2)     NULL DEFAULT NULL,
  `rank`               INT              NULL DEFAULT NULL,
  `is_employee_of_month` TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at`         TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_achievements_period` (`employee_id`,`academic_year_id`,`month`,`year`),
  KEY `idx_monthly_achievements_period`   (`academic_year_id`,`month`,`year`),
  KEY `idx_monthly_achievements_employee_of_month` (`is_employee_of_month`),
  CONSTRAINT `monthly_achievements_employee_id_foreign`      FOREIGN KEY (`employee_id`)      REFERENCES `employees`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `monthly_achievements_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Monthly ranking/achievement results. Frozen per period (one row per employee-month).';

-- ============================================================================
-- GROUP 11 — NOTIFICATIONS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 33 notifications
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       VARCHAR(40)     NOT NULL
               COMMENT 'task_assigned | deadline_reminder | task_overdue | report_submitted | report_approved | report_rejected | revision_required | achievement | badge_awarded | system',
  `title`      VARCHAR(200)    NOT NULL,
  `message`    TEXT            NULL,
  `data`       JSON            NULL,
  `read_at`    TIMESTAMP       NULL DEFAULT NULL,
  `created_at` TIMESTAMP       NULL DEFAULT NULL,
  `updated_at` TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  KEY `idx_notifications_type`         (`type`),
  KEY `idx_notifications_read_at`      (`read_at`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='User notifications. Cascade on user deletion is acceptable here (a removed account has no inbox).';

-- ============================================================================
-- GROUP 12 — AUDIT & ACTIVITY
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 34 audit_logs — important system/history changes.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NULL
                COMMENT 'NULL for system/automated events.',
  `action`      VARCHAR(100)    NOT NULL
                COMMENT 'e.g. employee.updated, report.approved, task.assigned, user.role_changed',
  `entity_type` VARCHAR(150)    NULL
                COMMENT 'Polymorphic type, e.g. App\\Models\\Report.',
  `entity_id`   BIGINT UNSIGNED NULL,
  `old_values`  JSON            NULL,
  `new_values`  JSON            NULL,
  `ip_address`  VARCHAR(45)     NULL DEFAULT NULL
                COMMENT 'IPv4 or IPv6.',
  `user_agent`  VARCHAR(255)    NULL DEFAULT NULL,
  `created_at`  TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_logs_action`  (`action`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_user_created` (`user_id`,`created_at`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable-ish audit trail of important changes. RESTRICT delete to protect history.';
-- NOTE: entity_id is polymorphic and intentionally NOT a foreign key.

-- ----------------------------------------------------------------------------
-- 35 activity_logs — broad user/system activity stream.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NULL
                  COMMENT 'NULL for system events.',
  `activity_type` VARCHAR(50)     NOT NULL
                  COMMENT 'e.g. login, logout, view_report, upload_report, submit_report, approve_report, reject_report',
  `description`   TEXT            NULL,
  `subject_type`  VARCHAR(150)    NULL
                  COMMENT 'Polymorphic type.',
  `subject_id`    BIGINT UNSIGNED NULL,
  `metadata`      JSON            NULL,
  `ip_address`    VARCHAR(45)     NULL DEFAULT NULL,
  `user_agent`    VARCHAR(255)    NULL DEFAULT NULL,
  `created_at`    TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_user_created`  (`user_id`,`created_at`),
  KEY `idx_activity_logs_activity_type` (`activity_type`),
  KEY `idx_activity_logs_subject`       (`subject_type`,`subject_id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='General activity stream (login/logout/actions). RESTRICT delete to protect history.';
-- NOTE: subject_id is polymorphic and intentionally NOT a foreign key.
-- ============================================================================
-- JPC PORTAL — PHASE 3.3-A
-- PLANNING & TASK MANAGEMENT — DATABASE DESIGN + SQL
--
-- Project : JPC Portal: AI-Driven Employee Progress Monitoring Dashboard
--           Using NLP-Based Algorithm for John Paul College
-- Stack   : Laravel 12 / MySQL 8 / XAMPP local
-- Database: acadtrack_db
--
-- This file is the DESIGN ARTIFACT for Phase 3.3-A (database design + SQL ONLY).
-- It follows the existing Phase 3.1/3.2 architecture:
--   * FKs: ON DELETE RESTRICT, ON UPDATE CASCADE (project convention).
--   * Controlled values are VARCHAR + documented sets (NO ENUM).
--   * utf8mb4 / InnoDB.
--
-- Existing tables are marked "EXISTING TABLE — REUSED" and are NOT recreated.
-- Only new tables and additive ALTERs appear in this file.
-- ----------------------------------------------------------------------------
-- DESIGN DECISION (reuse, per spec §68 "reuse existing architecture"):
--   * annual_plans ALREADY carries academic_year_id + department_id, so it is
--     itself the department plan. A separate department_plans table would be a
--     duplicate department structure → NOT created. department_targets records
--     reference annual_plans (the department-scoped plan).
--   * task_assignments.progress_percentage (DECIMAL 5,2, 0.00–100.00) already
--     implements the spec §25 "completion_percentage" concept → kept as-is.
--   * tasks.department_id already implements the spec "department_plan_id"
--     linkage (annual plan owns a department).
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `acadtrack_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `acadtrack_db`;

-- ============================================================================
-- SECTION 1 — EXISTING TABLES — REUSED (no DDL)
-- ============================================================================
--
-- EXISTING TABLE — REUSED: academic_years
--   id, name (UNIQUE), start_date, end_date, status (draft|active|closed),
--   timestamps. Spec §2 satisfied as-is.
--
-- EXISTING TABLE — REUSED: annual_plans
--   id, academic_year_id → academic_years, department_id → departments,
--   title, description, status (draft|submitted|under_review|approved|
--   rejected|completed|archived), created_by → users, approved_by → users,
--   approved_at, timestamps, deleted_at. Serves as the department plan
--   (department-scoped by department_id).
--
-- EXISTING TABLE — REUSED: employee_targets
--   id, annual_plan_id → annual_plans, employee_id → employees,
--   title ≡ target_name, description ≡ target_description,
--   target_value DECIMAL(12,2), target_unit ≡ unit, start_date, end_date,
--   status (draft|active|completed|cancelled), timestamps, deleted_at.
--   EXTENDED BELOW with department_target_id + created_by.
--
-- EXISTING TABLE — REUSED: tasks
--   id, annual_plan_id → annual_plans, department_id → departments,
--   created_by → users, title, description, priority (low|medium|high|urgent),
--   start_date, due_date, target_value, target_unit, required_output,
--   status, completed_at, timestamps, deleted_at.
--   EXTENDED BELOW with task_code, department_target_id, employee_target_id,
--   parent_task_id, estimated_hours, updated_by.
--
-- EXISTING TABLE — REUSED: task_assignments
--   id, task_id → tasks, employee_id → employees, assigned_by → users,
--   assigned_at, start_date, due_date, progress_percentage DECIMAL(5,2)
--   (= spec "completion_percentage"), status, completed_at, timestamps,
--   UNIQUE(task_id, employee_id). EXTENDED BELOW with accepted_at, remarks,
--   deleted_at.
--
-- EXISTING TABLE — REUSED: task_references
--   id, task_id → tasks, title, description, reference_type,
--   reference_url, file_path, timestamps, deleted_at. Reference documents.
--   task_attachments (new) handles file-upload metadata per spec §31.
--
-- EXISTING TABLES — REUSED: users, roles, permissions, departments,
--   positions, employees, employee_histories, employee_performance_records,
--   department_heads, badges, employee_badges, notifications, audit_logs,
--   activity_logs, report-related tables (all Phase 3.1/3.2, untouched).

-- ============================================================================
-- SECTION 2 — NEW TABLE: department_targets
-- One department-scoped goal per department plan (annual_plans).
-- Employee targets can optionally reference one department target.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `department_targets` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `annual_plan_id`     BIGINT UNSIGNED NOT NULL
                       COMMENT 'Department plan = annual_plans (department-scoped).',
  `department_id`      BIGINT UNSIGNED NOT NULL,
  `target_name`        VARCHAR(255)    NOT NULL,
  `target_description` TEXT            NULL,
  `target_value`       DECIMAL(12,2)   NULL,
  `unit`               VARCHAR(50)     NULL,
  `start_date`         DATE            NULL,
  `end_date`           DATE            NULL,
  `status`             VARCHAR(20)     NOT NULL DEFAULT 'draft'
                       COMMENT 'draft | active | completed | cancelled',
  `created_by`         BIGINT UNSIGNED NULL
                       COMMENT 'users.id of the creator (usually the department head).',
  `created_at`         TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
  `deleted_at`         TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_department_targets_annual_plan_id` (`annual_plan_id`),
  KEY `idx_department_targets_department_id` (`department_id`),
  KEY `idx_department_targets_status` (`status`),
  KEY `idx_department_targets_created_by` (`created_by`),
  CONSTRAINT `department_targets_annual_plan_id_foreign`
    FOREIGN KEY (`annual_plan_id`) REFERENCES `annual_plans` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `department_targets_department_id_foreign`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `department_targets_created_by_foreign`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Department-level goals within a department plan (annual_plans).';

-- ============================================================================
-- SECTION 3 — ALTER EXISTING: employee_targets
-- Adds optional department-target contribution link + audit author.
-- ============================================================================
ALTER TABLE `employee_targets`
  ADD COLUMN `department_target_id` BIGINT UNSIGNED NULL
    COMMENT 'Optional: the department target this employee target contributes to.'
    AFTER `annual_plan_id`,
  ADD COLUMN `created_by` BIGINT UNSIGNED NULL
    COMMENT 'users.id of the creator.'
    AFTER `status`,
  ADD KEY `idx_employee_targets_department_target_id` (`department_target_id`),
  ADD KEY `idx_employee_targets_created_by` (`created_by`),
  ADD CONSTRAINT `employee_targets_department_target_id_foreign`
    FOREIGN KEY (`department_target_id`) REFERENCES `department_targets` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `employee_targets_created_by_foreign`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- ============================================================================
-- SECTION 4 — ALTER EXISTING: tasks
-- Adds human-readable unique code, optional target linkages, subtask self-FK,
-- estimated effort and the last-editor audit column.
-- ============================================================================
ALTER TABLE `tasks`
  ADD COLUMN `task_code` VARCHAR(50) NULL
    COMMENT 'Unique human-readable code, e.g. TASK-2026-0001.'
    AFTER `id`,
  ADD COLUMN `department_target_id` BIGINT UNSIGNED NULL
    COMMENT 'Optional: task that operationalizes a department target.'
    AFTER `department_id`,
  ADD COLUMN `employee_target_id` BIGINT UNSIGNED NULL
    COMMENT 'Optional: task directly connected to one employee target.'
    AFTER `department_target_id`,
  ADD COLUMN `parent_task_id` BIGINT UNSIGNED NULL
    COMMENT 'Nullable self-FK: subtasks nest under a parent task.'
    AFTER `employee_target_id`,
  ADD COLUMN `estimated_hours` DECIMAL(8,2) NULL
    COMMENT 'Planned effort, supports fractional hours (e.g. 2.50).'
    AFTER `due_date`,
  ADD COLUMN `updated_by` BIGINT UNSIGNED NULL
    COMMENT 'users.id of the last editor.'
    AFTER `created_by`,
  ADD UNIQUE INDEX `uq_tasks_task_code` (`task_code`),
  ADD KEY `idx_tasks_department_target_id` (`department_target_id`),
  ADD KEY `idx_tasks_employee_target_id` (`employee_target_id`),
  ADD KEY `idx_tasks_parent_task_id` (`parent_task_id`),
  ADD KEY `idx_tasks_updated_by` (`updated_by`),
  ADD CONSTRAINT `tasks_department_target_id_foreign`
    FOREIGN KEY (`department_target_id`) REFERENCES `department_targets` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_employee_target_id_foreign`
    FOREIGN KEY (`employee_target_id`) REFERENCES `employee_targets` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_parent_task_id_foreign`
    FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_updated_by_foreign`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- Backfill task_code for pre-existing rows, then enforce NOT NULL.
UPDATE `tasks`
   SET `task_code` = CONCAT('TASK-', YEAR(COALESCE(`created_at`, NOW())), '-', LPAD(`id`, 4, '0'))
 WHERE `task_code` IS NULL;

ALTER TABLE `tasks`
  MODIFY COLUMN `task_code` VARCHAR(50) NOT NULL;

-- ============================================================================
-- SECTION 5 — ALTER EXISTING: task_assignments
-- Adds acceptance timestamp, free-text remarks, and soft delete (protects the
-- historical chain task → assignment → progress).
-- ============================================================================
ALTER TABLE `task_assignments`
  ADD COLUMN `accepted_at` TIMESTAMP NULL
    COMMENT 'When the employee accepted the assignment.'
    AFTER `assigned_at`,
  ADD COLUMN `remarks` TEXT NULL
    COMMENT 'Free-text note on the assignment.'
    AFTER `status`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL
    COMMENT 'Soft delete so historical progress is never destroyed.',
  ADD KEY `idx_task_assignments_accepted_at` (`accepted_at`);

-- ============================================================================
-- SECTION 6 — NEW TABLE: task_progress
-- Append-only history of assignment progress. One row per progress update,
-- so "0% → 25% → 50% → 75% → 100%" is fully reconstructible.
-- NEVER hard-deleted (audit/history); FK RESTRICT protects the chain.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `task_progress` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`             BIGINT UNSIGNED NOT NULL,
  `task_assignment_id`  BIGINT UNSIGNED NOT NULL,
  `employee_id`         BIGINT UNSIGNED NOT NULL,
  `previous_percentage` DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
  `new_percentage`      DECIMAL(5,2)    NOT NULL,
  `status`              VARCHAR(30)     NULL
                        COMMENT 'Assignment status snapshot at this progress point.',
  `comment`             TEXT            NULL,
  `updated_by`          BIGINT UNSIGNED NULL
                        COMMENT 'users.id of the actor recording the update.',
  `created_at`          TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`          TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_progress_task_id` (`task_id`),
  KEY `idx_task_progress_task_assignment_id` (`task_assignment_id`),
  KEY `idx_task_progress_employee_id` (`employee_id`),
  KEY `idx_task_progress_created_at` (`created_at`),
  CONSTRAINT `task_progress_task_id_foreign`
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_progress_task_assignment_id_foreign`
    FOREIGN KEY (`task_assignment_id`) REFERENCES `task_assignments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_progress_employee_id_foreign`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_progress_updated_by_foreign`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Append-only task assignment progress history.';

-- ============================================================================
-- SECTION 7 — NEW TABLE: task_comments
-- Communication among Admin / HR / Department Head / Employee about a task.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`            BIGINT UNSIGNED NOT NULL,
  `task_assignment_id` BIGINT UNSIGNED NULL
                       COMMENT 'Optional: comment tied to one assignment.',
  `user_id`            BIGINT UNSIGNED NOT NULL,
  `comment`            TEXT            NOT NULL,
  `created_at`         TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
  `deleted_at`         TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_comments_task_id` (`task_id`),
  KEY `idx_task_comments_task_assignment_id` (`task_assignment_id`),
  KEY `idx_task_comments_user_id` (`user_id`),
  CONSTRAINT `task_comments_task_id_foreign`
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_comments_task_assignment_id_foreign`
    FOREIGN KEY (`task_assignment_id`) REFERENCES `task_assignments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_comments_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Task-level communication thread.';

-- ============================================================================
-- SECTION 8 — NEW TABLE: task_attachments
-- File-upload metadata only. Actual files live in Laravel storage.
-- task_references (existing) keeps external/document references.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `task_attachments` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`            BIGINT UNSIGNED NOT NULL,
  `task_assignment_id` BIGINT UNSIGNED NULL
                       COMMENT 'Optional: attachment tied to one assignment/submission.',
  `uploaded_by`        BIGINT UNSIGNED NOT NULL
                       COMMENT 'users.id of the uploader.',
  `file_name`          VARCHAR(255)    NOT NULL,
  `file_path`          VARCHAR(500)    NOT NULL
                       COMMENT 'Storage-relative path; no binary in MySQL.',
  `mime_type`          VARCHAR(100)    NULL,
  `file_size`          BIGINT UNSIGNED NULL
                       COMMENT 'Size in bytes.',
  `created_at`         TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
  `deleted_at`         TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_attachments_task_id` (`task_id`),
  KEY `idx_task_attachments_task_assignment_id` (`task_assignment_id`),
  KEY `idx_task_attachments_uploaded_by` (`uploaded_by`),
  CONSTRAINT `task_attachments_task_id_foreign`
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_attachments_task_assignment_id_foreign`
    FOREIGN KEY (`task_assignment_id`) REFERENCES `task_assignments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_attachments_uploaded_by_foreign`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Task file-upload metadata (storage-backed, no binary).';

-- ============================================================================
-- SECTION 9 — NEW TABLE: task_reviews
-- Review/approval history per assignment. Audit trail; never soft-deleted.
-- Reviewer is Admin or Department Head per later authorization rules.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `task_reviews` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`            BIGINT UNSIGNED NOT NULL,
  `task_assignment_id` BIGINT UNSIGNED NOT NULL,
  `reviewed_by`        BIGINT UNSIGNED NOT NULL
                       COMMENT 'users.id of the reviewer.',
  `decision`           VARCHAR(20)     NOT NULL
                       COMMENT 'approved | rejected | needs_revision',
  `remarks`            TEXT            NULL,
  `reviewed_at`        TIMESTAMP       NULL DEFAULT NULL,
  `created_at`         TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_reviews_task_id` (`task_id`),
  KEY `idx_task_reviews_task_assignment_id` (`task_assignment_id`),
  KEY `idx_task_reviews_reviewed_by` (`reviewed_by`),
  KEY `idx_task_reviews_decision` (`decision`),
  CONSTRAINT `task_reviews_task_id_foreign`
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_reviews_task_assignment_id_foreign`
    FOREIGN KEY (`task_assignment_id`) REFERENCES `task_assignments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `task_reviews_reviewed_by_foreign`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-assignment task review/approval history (audit trail).';

-- ============================================================================
-- END OF PHASE 3.3-A SQL
-- ----------------------------------------------------------------------------
-- Summary
--   New tables : department_targets, task_progress, task_comments,
--                task_attachments, task_reviews (5)
--   Altered    : employee_targets (+department_target_id, +created_by)
--                tasks (+task_code UNIQUE, +department_target_id,
--                     +employee_target_id, +parent_task_id,
--                     +estimated_hours, +updated_by)
--                task_assignments (+accepted_at, +remarks, +deleted_at)
--   Reused     : academic_years, annual_plans, tasks, task_assignments,
--                task_references + all Phase 3.1/3.2 core tables
--   Not created: department_plans (redundant — annual_plans.department_id
--                already provides the department-plan linkage)
-- ============================================================================
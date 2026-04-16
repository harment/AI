-- =============================================
-- ملف الترحيل: إضافة جدول محاولات الأسئلة
-- migration_add_question_attempts.sql
-- آمن للتشغيل على قاعدة بيانات موجودة (يتجاهل إذا كان الجدول موجوداً)
-- =============================================

USE dhakali_db;

CREATE TABLE IF NOT EXISTS question_attempts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    question_id   INT NOT NULL,
    lesson_id     INT NOT NULL,
    is_correct    TINYINT(1) NOT NULL DEFAULT 0,
    attempts_count INT NOT NULL DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(id)  ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id)   REFERENCES lessons(id)   ON DELETE CASCADE,
    INDEX idx_qa_student  (student_id),
    INDEX idx_qa_student_question (student_id, question_id),
    INDEX idx_qa_lesson   (lesson_id),
    INDEX idx_qa_question (question_id)
);

-- ضمان وجود الفهرس المركب في حال كان الجدول موجوداً مسبقاً
DROP PROCEDURE IF EXISTS _add_idx_qa_student_question;
DELIMITER //
CREATE PROCEDURE _add_idx_qa_student_question()
BEGIN
  IF EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'question_attempts'
  ) AND NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'question_attempts'
      AND INDEX_NAME   = 'idx_qa_student_question'
  ) THEN
    ALTER TABLE question_attempts
      ADD INDEX idx_qa_student_question (student_id, question_id);
  END IF;
END //
DELIMITER ;
CALL _add_idx_qa_student_question();
DROP PROCEDURE IF EXISTS _add_idx_qa_student_question;

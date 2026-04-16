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
    INDEX idx_qa_lesson   (lesson_id),
    INDEX idx_qa_question (question_id)
);

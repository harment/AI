-- =============================================
-- ملف الترحيل: ميزات التحليل المتقدمة
-- migration_enhanced_game_simple.sql
-- آمن للتشغيل على قاعدة بيانات موجودة
-- =============================================

USE dhakali_db;

-- 1. إضافة عمود presentation_pdf بشكل آمن (يتجاهل الخطأ إذا كان موجوداً)
DROP PROCEDURE IF EXISTS _add_col_presentation_pdf;
DELIMITER //
CREATE PROCEDURE _add_col_presentation_pdf()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'lessons'
      AND COLUMN_NAME  = 'presentation_pdf'
  ) THEN
    ALTER TABLE lessons
      ADD COLUMN presentation_pdf VARCHAR(500) DEFAULT NULL AFTER presentation_html;
  END IF;
END //
DELIMITER ;
CALL _add_col_presentation_pdf();
DROP PROCEDURE IF EXISTS _add_col_presentation_pdf;

-- 2. جدول إجابات الطلاب (تتبع كل إجابة على مستوى السؤال)
CREATE TABLE IF NOT EXISTS question_answers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    lesson_id     INT NOT NULL,
    question_id   INT NOT NULL,
    selected_opt  ENUM('a','b','c','d') NOT NULL,
    is_correct    TINYINT(1) NOT NULL DEFAULT 0,
    answered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(id)  ON DELETE CASCADE,
    FOREIGN KEY (lesson_id)   REFERENCES lessons(id)   ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_qa_student  (student_id),
    INDEX idx_qa_lesson   (lesson_id),
    INDEX idx_qa_question (question_id)
);

-- 3. إحصائيات ملخصة لكل سؤال (اختياري – يُحدَّث تلقائياً)
CREATE TABLE IF NOT EXISTS question_stats (
    question_id   INT PRIMARY KEY,
    total_answers INT NOT NULL DEFAULT 0,
    correct_count INT NOT NULL DEFAULT 0,
    last_updated  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

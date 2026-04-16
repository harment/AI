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

-- 2. جدول محاولات الطلاب (متوافق مع api/answers.php)
CREATE TABLE IF NOT EXISTS question_attempts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL,
    question_id    INT NOT NULL,
    lesson_id      INT NOT NULL,
    is_correct     TINYINT(1) NOT NULL DEFAULT 0,
    attempts_count INT NOT NULL DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(id)  ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id)   REFERENCES lessons(id)   ON DELETE CASCADE,
    INDEX idx_qa_student  (student_id),
    INDEX idx_qa_student_question (student_id, question_id),
    INDEX idx_qa_lesson   (lesson_id),
    INDEX idx_qa_question (question_id)
);

-- 3. إحصائيات ملخصة لكل سؤال (اختياري – يُحدَّث تلقائياً)
CREATE TABLE IF NOT EXISTS question_stats (
    question_id   INT PRIMARY KEY,
    total_answers INT NOT NULL DEFAULT 0,
    correct_count INT NOT NULL DEFAULT 0,
    wrong_count   INT NOT NULL DEFAULT 0,
    last_updated  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- 4. ضمان وجود عمود wrong_count في القواعد القديمة
DROP PROCEDURE IF EXISTS _add_col_question_stats_wrong_count;
DELIMITER //
CREATE PROCEDURE _add_col_question_stats_wrong_count()
BEGIN
  IF EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'question_stats'
  ) AND NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'question_stats'
      AND COLUMN_NAME  = 'wrong_count'
  ) THEN
    ALTER TABLE question_stats
      ADD COLUMN wrong_count INT NOT NULL DEFAULT 0 AFTER correct_count;
  END IF;
END //
DELIMITER ;
CALL _add_col_question_stats_wrong_count();
DROP PROCEDURE IF EXISTS _add_col_question_stats_wrong_count;

-- 5. ضمان وجود الفهرس المركب (student_id, question_id) في القواعد القديمة
-- ملاحظة: الفهرس موجود في CREATE TABLE أعلاه للتثبيتات الجديدة،
-- وهذه الخطوات مخصصة فقط للتثبيتات القديمة التي أُنشئ فيها الجدول قبل إضافة الفهرس.
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

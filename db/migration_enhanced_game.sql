-- =============================================
-- سكريبت تحديث قاعدة البيانات للنظام المحسّن
-- يُستخدم لتحديث قواعد البيانات الموجودة
-- =============================================

USE dhakali_db;

-- 1. إضافة نمط الجزيرة لخيارات نوع اللعبة
ALTER TABLE lessons 
MODIFY COLUMN game_type ENUM('mountain','maze','ship','island') NOT NULL DEFAULT 'mountain';

-- 2. إضافة عمود presentation_pdf إذا لم يكن موجوداً
ALTER TABLE lessons 
ADD COLUMN IF NOT EXISTS presentation_pdf VARCHAR(500) DEFAULT NULL AFTER presentation_html;

-- 3. إضافة عمود game_mode في جدول student_games
ALTER TABLE student_games 
ADD COLUMN IF NOT EXISTS game_mode VARCHAR(20) DEFAULT 'mountain' AFTER completed;

-- 4. إنشاء جدول تفاصيل إجابات الأسئلة
CREATE TABLE IF NOT EXISTS question_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    question_id INT NOT NULL,
    lesson_id INT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    attempts_count INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    INDEX idx_student_question (student_id, question_id),
    INDEX idx_lesson (lesson_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. إضافة فهارس لتحسين الأداء
ALTER TABLE student_games 
ADD INDEX IF NOT EXISTS idx_student_lesson_date (student_id, lesson_id, played_at);

ALTER TABLE student_games 
ADD INDEX IF NOT EXISTS idx_played_at (played_at);

-- 6. تحديث البيانات الموجودة
-- تعيين game_mode = 'mountain' للسجلات القديمة التي لا تحتوي على قيمة
UPDATE student_games 
SET game_mode = 'mountain' 
WHERE game_mode IS NULL OR game_mode = '';

-- عرض ملخص التحديثات
SELECT 
    'تم تحديث قاعدة البيانات بنجاح' as status,
    (SELECT COUNT(*) FROM question_attempts) as total_question_attempts,
    (SELECT COUNT(*) FROM student_games) as total_games,
    (SELECT COUNT(DISTINCT game_mode) FROM student_games) as game_modes_used;

-- إنشاء عرض (View) لتسهيل الاستعلامات الإحصائية
CREATE OR REPLACE VIEW v_question_difficulty AS
SELECT 
    q.id as question_id,
    q.lesson_id,
    q.question_text,
    COUNT(qa.id) as total_attempts,
    SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_attempts,
    SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) as wrong_attempts,
    ROUND(SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 2) as success_rate,
    AVG(qa.attempts_count) as avg_attempts,
    CASE 
        WHEN ROUND(SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 2) < 30 THEN 'صعب جداً'
        WHEN ROUND(SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 2) < 50 THEN 'صعب'
        WHEN ROUND(SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 2) < 70 THEN 'متوسط'
        WHEN ROUND(SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 2) < 85 THEN 'سهل'
        ELSE 'سهل جداً'
    END as difficulty_level
FROM questions q
LEFT JOIN question_attempts qa ON qa.question_id = q.id
GROUP BY q.id, q.lesson_id, q.question_text;

-- إنشاء عرض لإحصائيات الطلاب
CREATE OR REPLACE VIEW v_student_performance AS
SELECT 
    s.id as student_id,
    s.name as student_name,
    s.points,
    COUNT(DISTINCT sg.lesson_id) as lessons_played,
    COUNT(sg.id) as total_games,
    SUM(sg.completed) as total_wins,
    AVG(sg.points_earned) as avg_points_per_game,
    COUNT(DISTINCT ss.scholar_id) as scholars_discovered
FROM students s
LEFT JOIN student_games sg ON sg.student_id = s.id
LEFT JOIN student_scholars ss ON ss.student_id = s.id
GROUP BY s.id, s.name, s.points;

-- رسالة نهائية
SELECT 
    '✅ تم تحديث قاعدة البيانات بنجاح!' as message,
    'تم إضافة:' as info,
    '1. نمط الجزيرة للعبة' as feature_1,
    '2. تتبع تفصيلي لإجابات الأسئلة' as feature_2,
    '3. حد أقصى 3 محاولات يومياً' as feature_3,
    '4. تحليل صعوبة الأسئلة' as feature_4,
    '5. إحصائيات أداء محسّنة' as feature_5;

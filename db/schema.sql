-- =============================================
-- قاعدة بيانات: المساعد الذّكاليّ
-- =============================================

CREATE DATABASE IF NOT EXISTS dhakali_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dhakali_db;

-- جدول الطلاب
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    university_id VARCHAR(50) NOT NULL UNIQUE,
    level ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    study_year VARCHAR(20) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    points INT NOT NULL DEFAULT 0,
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول الأساتذة / المشرفين
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول المقررات
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(100) DEFAULT 'fa-book',
    color VARCHAR(20) DEFAULT '#4B8B3B',
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- جدول الدروس
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    pdf_url VARCHAR(500) DEFAULT NULL,
    podcast_url VARCHAR(500) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    presentation_html LONGTEXT DEFAULT NULL,
    presentation_pdf VARCHAR(500) DEFAULT NULL,
    infographic_url VARCHAR(500) DEFAULT NULL,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    game_type ENUM('mountain','maze','ship') NOT NULL DEFAULT 'mountain',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- جدول أسئلة الألعاب
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option ENUM('a','b','c','d') NOT NULL,
    feedback_correct TEXT DEFAULT NULL,
    feedback_wrong TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- جدول العلماء
CREATE TABLE IF NOT EXISTS scholars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    short_bio TEXT NOT NULL,
    era VARCHAR(100) DEFAULT NULL,
    works TEXT DEFAULT NULL,
    img_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول سجل لعب الطالب
CREATE TABLE IF NOT EXISTS student_games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    points_earned INT NOT NULL DEFAULT 0,
    scholar_id INT DEFAULT NULL,
    attempts INT NOT NULL DEFAULT 1,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE SET NULL
);

-- جدول العلماء المكتشفين من الطالب
CREATE TABLE IF NOT EXISTS student_scholars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    scholar_id INT NOT NULL,
    discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_student_scholar (student_id, scholar_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE
);

-- جدول سجل نشاط الطلاب
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    duration_seconds INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
);

-- جدول تسجيل الدخول / الجلسات
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('student','teacher') NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- بيانات تجريبية: أستاذ افتراضي
-- البريد: admin@dhakali.edu
-- كلمة المرور: password
INSERT INTO teachers (name, email, password_hash) VALUES
('الأستاذ الإداري', 'admin@dhakali.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE
    password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    is_active = 1;

-- بيانات تجريبية: علماء النحو
INSERT INTO scholars (name, short_bio, era, works) VALUES
('سيبويه', 'إمام النحاة ومؤسس علم النحو العربي، واضع أول كتاب منهجي في النحو', 'القرن الثاني الهجري', 'كتاب سيبويه'),
('الخليل بن أحمد الفراهيدي', 'عالم لغوي وموسيقي، مؤسس علم العروض ومبتكر نظام النقط والشكل', 'القرن الثاني الهجري', 'كتاب العين، العروض'),
('الكسائي', 'إمام مدرسة الكوفة النحوية، أحد القراء السبعة', 'القرن الثاني الهجري', 'معاني القرآن، النوادر في اللغة'),
('ابن مالك', 'صاحب الألفية الشهيرة في النحو والصرف التي حفظها الملايين', 'القرن السابع الهجري', 'ألفية ابن مالك، شرح التسهيل'),
('ابن هشام الأنصاري', 'أعلم الناس بنحو العربية بعد سيبويه كما وصفه ابن خلدون', 'القرن الثامن الهجري', 'مغني اللبيب، أوضح المسالك'),
('الزمخشري', 'عالم متعدد العلوم في اللغة والنحو والبلاغة والتفسير', 'القرن السادس الهجري', 'المفصل في النحو، الكشاف'),
('ابن جني', 'من أبرز علماء النحو واللغة والفلسفة اللغوية', 'القرن الرابع الهجري', 'الخصائص، سر صناعة الإعراب')
ON DUPLICATE KEY UPDATE name = name;

-- بيانات تجريبية: مقرر ودرس أولي
INSERT INTO courses (teacher_id, name, description, icon, color) VALUES
(1, 'مقرر النحو التطبيقي', 'تعلم قواعد النحو العربي تطبيقياً لغير الناطقين', 'fa-scroll', '#4B8B3B')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO lessons (course_id, name, description, is_open, game_type, sort_order) VALUES
(1, 'المبتدأ والخبر', 'التعرف على ركني الجملة الاسمية وأحكامهما', 1, 'mountain', 1),
(1, 'الفعل وأنواعه', 'تصنيف الأفعال ومعرفة أزمنتها وخصائصها', 1, 'maze', 2),
(1, 'علامات الإعراب', 'تعلم علامات الرفع والنصب والجر والجزم', 0, 'ship', 3)
ON DUPLICATE KEY UPDATE name = name;

-- أسئلة تجريبية للدرس الأول
INSERT INTO questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option, feedback_correct, feedback_wrong) VALUES
(1, 'ما المبتدأ في الجملة: "العلمُ نورٌ"؟', 'نورٌ', 'العلمُ', 'في', 'الجملة كلها', 'b', 'أحسنت! المبتدأ هو الاسم المرفوع في أول الجملة الاسمية.', 'المبتدأ هو الاسم المرفوع في أول الجملة الاسمية، وهو هنا "العلمُ".'),
(1, 'ما علامة رفع المبتدأ في الجملة السابقة؟', 'الفتحة', 'الكسرة', 'الضمة', 'السكون', 'c', 'ممتاز! المبتدأ يُرفع بالضمة الظاهرة.', 'المبتدأ يُرفع بالضمة الظاهرة أو المقدرة.'),
(1, 'ما الخبر في الجملة: "الكتابُ مفيدٌ"؟', 'الكتابُ', 'مفيدٌ', 'كلاهما', 'لا يوجد خبر', 'b', 'رائع! الخبر هو ما يُخبر به عن المبتدأ.', 'الخبر هو الجزء الثاني من الجملة الاسمية الذي يُتمم المعنى.'),
(1, 'هل يمكن أن يتقدم الخبر على المبتدأ؟', 'لا، يجب أن يأتي المبتدأ أولاً', 'نعم، إذا كان الخبر شبه جملة', 'نعم، دائماً', 'لا، إطلاقاً', 'b', 'صحيح! يتقدم الخبر على المبتدأ في حالات محددة منها إذا كان شبه جملة.', 'يجوز تقديم الخبر على المبتدأ في حالات محددة كأن يكون الخبر شبه جملة.'),
(1, 'أيّ الجمل التالية جملة اسمية صحيحة؟', 'جاء الطالبُ', 'الطالبُ مجتهدٌ', 'يدرسُ الطالبُ', 'درسَ الطالبُ', 'b', 'أحسنت! الجملة الاسمية تبدأ باسم (مبتدأ).', 'الجملة الاسمية هي التي تبدأ باسم ولها ركنان: المبتدأ والخبر.'),
(1, 'ما نوع الخبر في: "الطالبُ في الفصلِ"؟', 'خبر مفرد', 'خبر جملة فعلية', 'خبر شبه جملة', 'خبر جملة اسمية', 'c', 'ممتاز! "في الفصلِ" جار ومجرور وهو شبه جملة.', 'الخبر شبه الجملة هو الجار والمجرور أو الظرف.'),
(1, 'ما المبتدأ في: "أنتَ طالبٌ مجتهدٌ"؟', 'طالبٌ', 'مجتهدٌ', 'أنتَ', 'طالبٌ مجتهدٌ', 'c', 'رائع! الضمير "أنتَ" هو المبتدأ.', 'الضمائر يمكن أن تكون مبتدأً، و"أنتَ" ضمير مرفوع في محل رفع مبتدأ.')
ON DUPLICATE KEY UPDATE question_text = question_text;

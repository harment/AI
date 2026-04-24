<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher  = requireTeacher();
$db       = getDB();
$lessonId = (int)($_GET['lesson_id'] ?? 0);
if (!$lessonId) { header('Location: /teacher/lessons.php'); exit; }

$lesson = $db->prepare("SELECT l.*, c.name AS course_name FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?");
$lesson->execute([$lessonId]);
$lesson = $lesson->fetch();
if (!$lesson) { header('Location: /teacher/lessons.php'); exit; }

$msg = ''; $msgType = 'success';

// Map of provider → env-stored key
$envKeys = [
    'gemini'      => defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '',
    'openai'      => defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '',
    'claude'      => defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '',
    'gamma'       => defined('GAMMA_API_KEY') ? GAMMA_API_KEY : '',
    'elevenlabs'  => defined('ELEVENLABS_API_KEY') ? ELEVENLABS_API_KEY : '',
    'heygen'      => defined('HEYGEN_API_KEY') ? HEYGEN_API_KEY : '',
    'flux'        => defined('FLUX_API_KEY') ? FLUX_API_KEY : '',
];

// ========== AJAX: فحص حالة الفيديو ==========
if (isset($_GET['check_video_status']) && !empty($_GET['lesson_id'])) {
    @ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    try {
        $lessonId = (int)$_GET['lesson_id'];
        $lesson = $db->prepare("SELECT video_url FROM lessons WHERE id=?");
        $lesson->execute([$lessonId]);
        $lesson = $lesson->fetch();
        
        if (!$lesson || empty($lesson['video_url'])) {
            echo json_encode(['status' => 'not_found']);
            exit;
        }
        
        $videoData = @json_decode($lesson['video_url'], true);
        
        // إذا كان نص عادي (URL) - الفيديو جاهز
        if (!$videoData) {
            echo json_encode([
                'status' => 'completed',
                'video_url' => $lesson['video_url']
            ]);
            exit;
        }
        
        // إذا كان JSON - تحقق من الحالة
        if (isset($videoData['status']) && $videoData['status'] === 'completed') {
            echo json_encode([
                'status' => 'completed',
                'video_url' => $videoData['video_url'] ?? $videoData['embed_url'] ?? ''
            ]);
            exit;
        }
        
        if (isset($videoData['status']) && $videoData['status'] === 'failed') {
            echo json_encode([
                'status' => 'failed',
                'error' => $videoData['error'] ?? 'فشل التوليد'
            ]);
            exit;
        }
        
        // إذا كان processing - تحقق من HeyGen
        if (isset($videoData['status']) && $videoData['status'] === 'processing' && !empty($videoData['video_id'])) {
            $heygenKey = defined('HEYGEN_API_KEY') && !empty(HEYGEN_API_KEY) ? HEYGEN_API_KEY : '';
            
            if (!empty($heygenKey)) {
                $videoId = $videoData['video_id'];
                $url = "https://api.heygen.com/v3/videos/" . urlencode($videoId);
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'X-Api-Key: ' . $heygenKey,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_TIMEOUT => 10
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    $status = $data['data']['status'] ?? $data['status'] ?? 'processing';
                    
                    if ($status === 'completed') {
                        $directVideoUrl = $data['data']['video_url'] 
                                       ?? $data['data']['url'] 
                                       ?? $data['video_url']
                                   ?? $data['url'] 
                                   ?? '';
                    
                    // إذا لم نجد video_url، استخدم embed URL
                    if (empty($directVideoUrl)) {
                        $embedUrl = "https://app.heygen.com/embeds/" . $videoId;
                        $videoUrl = $embedUrl;
                    } else {
                        $videoUrl = $directVideoUrl;
                    }
                    
                    // تحديث قاعدة البيانات
                    $embedUrl = "https://app.heygen.com/embeds/" . $videoId;
                    $videoData['status'] = 'completed';
                    $videoData['video_url'] = $videoUrl;
                    $videoData['embed_url'] = $embedUrl;
                    $videoData['completed_at'] = date('Y-m-d H:i:s');
                    $videoDataJson = json_encode($videoData, JSON_UNESCAPED_UNICODE);
                    $db->prepare("UPDATE lessons SET video_url=? WHERE id=?")->execute([$videoDataJson, $lessonId]);
                    
                    echo json_encode([
                        'status' => 'completed',
                        'video_url' => $videoUrl,
                        'embed_url' => $embedUrl
                    ]);
                    exit;
                } elseif ($status === 'failed' || $status === 'error') {
                    $errorMsg = $data['data']['error'] ?? $data['error'] ?? 'فشل توليد الفيديو في HeyGen';
                    
                    // تحديث قاعدة البيانات
                    $videoData['status'] = 'failed';
                    $videoData['error'] = $errorMsg;
                    $videoData['failed_at'] = date('Y-m-d H:i:s');
                    $videoDataJson = json_encode($videoData, JSON_UNESCAPED_UNICODE);
                    $db->prepare("UPDATE lessons SET video_url=? WHERE id=?")->execute([$videoDataJson, $lessonId]);
                    
                    echo json_encode([
                        'status' => 'failed',
                        'error' => $errorMsg
                    ]);
                    exit;
                }
            }
        }
    }
    
    // لا يزال قيد المعالجة
    echo json_encode(['status' => 'processing']);
    exit;
    
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
        exit;
    }
}

// After form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $genType  = $_POST['gen_type'] ?? '';
    $provider = $_POST['provider'] ?? 'gemini';
    $submittedKey = trim($_POST['api_key_override'] ?? '');
    $apiKey = $submittedKey ?: ($envKeys[$provider] ?? '');

    // ========== QUESTIONS ==========
    if ($genType === 'questions') {
        $pdfContent = '';
        if (!empty($lesson['pdf_url'])) {
            $pdfPath = UPLOAD_DIR . 'pdfs/' . basename($lesson['pdf_url']);
            $pdfContent = extractPdfText($pdfPath);
        }

        if (empty($pdfContent)) {
            $msg = 'لم يتم العثور على ملف PDF للدرس. يرجى رفع ملف PDF أولاً.';
            $msgType = 'warning';
        } else {
            $truncatedContent = mb_substr($pdfContent, 0, 12000);
            $prompt = "أنت أستاذ لغة عربية متخصص في النحو. فيما يلي المحتوى الكامل لملف PDF للدرس «{$lesson['name']}»:\n\n{$truncatedContent}\n\nبناءً على هذا المحتوى فقط، اصنع 30 سؤال اختيار من متعدد باللغة العربية الفصحى. كل سؤال له 4 خيارات وإجابة صحيحة وتغذية راجعة.\n\nأجب بـ JSON array فقط:\n[{\"question_text\":\"...\",\"option_a\":\"...\",\"option_b\":\"...\",\"option_c\":\"...\",\"option_d\":\"...\",\"correct_option\":\"a\",\"feedback_correct\":\"...\",\"feedback_wrong\":\"...\"}]";
            
            $result = callAI($prompt, $apiKey, $provider);
            
            if ($result['success']) {
                $cleanText = cleanJsonResponse($result['text']);
                $parsed = @json_decode($cleanText, true);
                
                if (is_array($parsed) && json_last_error() === JSON_ERROR_NONE) {
                    $count = 0;
                    foreach ($parsed as $q) {
                        if (!empty($q['question_text'])) {
                            $db->prepare("INSERT INTO questions (lesson_id,question_text,option_a,option_b,option_c,option_d,correct_option,feedback_correct,feedback_wrong) VALUES (?,?,?,?,?,?,?,?,?)")
                               ->execute([
                                   $lessonId, 
                                   $q['question_text'], 
                                   $q['option_a'] ?? '', 
                                   $q['option_b'] ?? '', 
                                   $q['option_c'] ?? '', 
                                   $q['option_d'] ?? '', 
                                   $q['correct_option'] ?? 'a', 
                                   $q['feedback_correct'] ?? '', 
                                   $q['feedback_wrong'] ?? ''
                               ]);
                            $count++;
                        }
                    }
                    $msg = 'تم توليد ' . $count . ' سؤال بنجاح! يمكنك الآن مراجعتها وتعديلها.';
                } else {
                    $msg = 'فشل تحليل JSON. يرجى المحاولة مرة أخرى.';
                    $msgType = 'warning';
                }
            } else {
                $msg = 'فشل الاستدعاء: ' . $result['error'];
                $msgType = 'danger';
            }
        }
    }

    // ========== PRESENTATION ==========
    if ($genType === 'presentation') {
        $presentationContent = '';
        
        if (isset($_FILES['presentation_pdf']) && $_FILES['presentation_pdf']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['presentation_pdf']['tmp_name'];
            $presentationContent = extractPdfText($tmpPath);
        } elseif (!empty($_POST['content_summary'])) {
            $presentationContent = trim($_POST['content_summary']);
        } else {
            $msg = 'يرجى رفع ملف PDF أو إدخال ملخص المحتوى.';
            $msgType = 'warning';
        }
        
        if (!empty($presentationContent)) {
            $truncatedContent = mb_substr($presentationContent, 0, 8000);
            
            if ($provider === 'gamma') {
                $result = generateGammaPresentation($lesson['name'], $truncatedContent, $apiKey, $lessonId, $db);
                if ($result['success']) {
                    $msg = 'تم توليد العرض التقديمي بنجاح عبر Gamma! ';
                    $msg .= '<a href="' . htmlspecialchars($result['gamma_url']) . '" target="_blank" class="btn btn-sm btn-primary" style="margin:0 .5rem;"><i class="fas fa-external-link-alt"></i> فتح في Gamma</a>';
                    if (!empty($result['export_url'])) {
                        $msg .= '<a href="' . htmlspecialchars($result['export_url']) . '" download class="btn btn-sm btn-success"><i class="fas fa-download"></i> تحميل PDF</a>';
                    }
                } else {
                    $msg = 'فشل توليد العرض عبر Gamma: ' . $result['error'];
                    $msgType = 'danger';
                }
            } else {
                // توليد HTML متجاوب مع أزرار تنقل واضحة
                $prompt = <<<PROMPT
أنت مصمم عروض تقديمية احترافية. الدرس: «{$lesson['name']}».

المحتوى:
{$truncatedContent}

اصنع عرضاً تقديمياً HTML تفاعلياً يتضمن 10 شرائح.

**متطلبات التصميم الإلزامية:**

1. **أبعاد الشرائح الموحدة:**
   - عرض ثابت: 900px (max-width)
   - ارتفاع ثابت: 600px
   - نسبة العرض للارتفاع: 3:2 لجميع الشرائح

2. **أزرار التنقل الواضحة:**
   - زر "السابق ←" في اليسار
   - زر "التالي →" في اليمين
   - حجم كبير: 60px × 60px على الأقل
   - ألوان بارزة (خلفية داكنة، نص أبيض)
   - موضعة: position fixed على جانبي الشاشة

3. **مؤشر التقدم:**
   - مركزي في الأسفل
   - نص واضح: "الشريحة X من 10"
   - حجم خط: 18px على الأقل

4. **التجاوب (Responsive):**
   - على الجوال (max-width: 768px):
     * عرض: 100%
     * ارتفاع: auto (مع حفظ النسبة)
     * أزرار أصغر: 50px × 50px
     * خط أصغر: 14px

5. **التنقل بالكيبورد:**
   - السهم الأيمن ← الشريحة التالية
   - السهم الأيسر → الشريحة السابقة
   - لكن الأزرار يجب أن تكون واضحة وكبيرة!

6. **الألوان:**
   - خلفية الشرائح: #f8f9fa أو لون فاتح
   - النصوص: #212529 (داكن)
   - العناوين: #1a73e8 أو لون أساسي جميل
   - الأزرار: خلفية #1a73e8، نص #ffffff

7. **الخطوط:**
   - استخدم خطوط عربية واضحة: 'Cairo', 'Tajawal', 'IBM Plex Sans Arabic', sans-serif
   - حجم العنوان: 32px
   - حجم النص: 20px
   - تباعد سطور: 1.6

**الكود المطلوب:**
- HTML5 كامل بـ DOCTYPE
- CSS مدمج في <style>
- JavaScript للتنقل بين الشرائح
- جميع الشرائح مخفية إلا الأولى
- لا تستخدم مكتبات خارجية (كل شيء مدمج)

**أرجع HTML كامل جاهز للعرض فوراً.**
PROMPT;
                
                $result = callAI($prompt, $apiKey, $provider);
                if ($result['success']) {
                    $htmlContent = $result['text'];
                    
                    // استخراج HTML
                    if (strpos($htmlContent, '```html') !== false) {
                        preg_match('/```html\s*(.*?)\s*```/s', $htmlContent, $matches);
                        $htmlContent = $matches[1] ?? $htmlContent;
                    } elseif (strpos($htmlContent, '```') !== false) {
                        preg_match('/```\s*(.*?)\s*```/s', $htmlContent, $matches);
                        $htmlContent = $matches[1] ?? $htmlContent;
                    }
                    
                    // حفظ HTML في ملف منفصل للعرض التفاعلي
                    $timestamp = time();
                    $htmlFileName = 'presentation_' . $lessonId . '_' . $timestamp . '.html';
                    $htmlFilePath = UPLOAD_DIR . 'presentations/' . $htmlFileName;
                    
                    // إنشاء مجلد presentations إذا لم يكن موجوداً
                    if (!file_exists(UPLOAD_DIR . 'presentations/')) {
                        mkdir(UPLOAD_DIR . 'presentations/', 0755, true);
                    }
                    
                    file_put_contents($htmlFilePath, $htmlContent);
                    $htmlUrl = '/uploads/presentations/' . $htmlFileName;
                    
                    // حفظ البيانات بصيغة JSON تحتوي على المعلومات
                    $presentationData = json_encode([
                        'type' => 'html',
                        'provider' => $provider,
                        'html_url' => $htmlUrl,
                        'created_at' => date('Y-m-d H:i:s')
                    ], JSON_UNESCAPED_UNICODE);
                    
                    $db->prepare("UPDATE lessons SET presentation_html=? WHERE id=?")->execute([$presentationData, $lessonId]);
                    
                    $msg = 'تم توليد العرض التقديمي بنجاح! ';
                    $msg .= '<a href="' . $htmlUrl . '" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-external-link-alt"></i> معاينة العرض</a>';
                } else {
                    $msg = 'فشل توليد العرض: ' . $result['error']; 
                    $msgType = 'danger';
                }
            }
        }
    }

    // ========== PODCAST ==========
    if ($genType === 'podcast') {
        // خيار 1: رابط مباشر
        $podcastUrl = trim($_POST['podcast_url_direct'] ?? '');
        
        if (!empty($podcastUrl)) {
            // حفظ الرابط مباشرة
            $db->prepare("UPDATE lessons SET podcast_url=? WHERE id=?")->execute([$podcastUrl, $lessonId]);
            $msg = 'تم حفظ رابط البودكاست بنجاح!';
        }
        // خيار 2: رفع ملف
        elseif (isset($_FILES['podcast_file']) && $_FILES['podcast_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['podcast_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['mp3', 'm4a', 'wav', 'ogg'])) {
                $msg = 'صيغة الملف غير مدعومة. يرجى رفع MP3, M4A, WAV أو OGG';
                $msgType = 'danger';
            } else {
                $safeFileName = 'podcast_lesson_' . $lessonId . '_' . time() . '.' . $ext;
                $uploadPath = UPLOAD_DIR . 'podcasts/';
                
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $filePath = $uploadPath . $safeFileName;
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $podcastUrl = '/uploads/podcasts/' . $safeFileName;
                    $db->prepare("UPDATE lessons SET podcast_url=? WHERE id=?")->execute([$podcastUrl, $lessonId]);
                    $msg = 'تم رفع ملف البودكاست بنجاح!';
                } else {
                    $msg = 'فشل رفع الملف.';
                    $msgType = 'danger';
                }
            }
        }
        // خيار 3: توليد من PDF
        elseif (!empty($_POST['generate_podcast_from_pdf'])) {
            $pdfContent = '';
            
            // التحقق من وجود PDF الدرس
            if (!empty($lesson['pdf_url'])) {
                $pdfPath = UPLOAD_DIR . 'pdfs/' . basename($lesson['pdf_url']);
                $pdfContent = extractPdfText($pdfPath);
            }
            
            if (empty($pdfContent)) {
                $msg = 'لم يتم العثور على ملف PDF للدرس. يرجى رفع PDF أولاً.';
                $msgType = 'warning';
            } else {
                // استخدام المزود المختار للصوت
                $audioProvider = $provider; // elevenlabs or openai_tts
                $result = generatePodcast($lesson['name'], $pdfContent, $apiKey, $audioProvider, $lessonId, $db);
                if ($result['success']) {
                    $msg = 'تم توليد البودكاست بنجاح! <a href="' . htmlspecialchars($result['podcast_url']) . '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-headphones"></i> استماع</a>';
                } else {
                    $msg = 'فشل توليد البودكاست: ' . $result['error'];
                    $msgType = 'danger';
                }
            }
        } else {
            $msg = 'يرجى إدخال رابط أو رفع ملف أو طلب التوليد.';
            $msgType = 'warning';
        }
    }

    // ========== VIDEO ==========
    if ($genType === 'video') {
        // خيار 1: رابط يوتيوب مباشر
        $videoUrl = trim($_POST['video_url_direct'] ?? '');
        
        if (!empty($videoUrl)) {
            // حفظ الرابط مباشرة
            $db->prepare("UPDATE lessons SET video_url=? WHERE id=?")->execute([$videoUrl, $lessonId]);
            $msg = 'تم حفظ رابط الفيديو بنجاح!';
        }
        // خيار 2: توليد من PDF عبر HeyGen
        elseif (!empty($_POST['generate_video_from_pdf'])) {
            $pdfContent = '';
            
            if (!empty($lesson['pdf_url'])) {
                $pdfPath = UPLOAD_DIR . 'pdfs/' . basename($lesson['pdf_url']);
                $pdfContent = extractPdfText($pdfPath);
            }
            
            if (empty($pdfContent)) {
                $msg = 'لم يتم العثور على ملف PDF للدرس. يرجى رفع PDF أولاً.';
                $msgType = 'warning';
            } else {
                $result = generateVideo($lesson['name'], $pdfContent, $apiKey, $lessonId, $db);
                if ($result['success']) {
                    if (isset($result['status']) && $result['status'] === 'processing') {
                        $msg = '<i class="fas fa-spinner fa-spin"></i> بدأ توليد الفيديو بنجاح! جارٍ المعالجة... سيتم التحديث تلقائياً.';
                        $msgType = 'info';
                        echo '<meta http-equiv="refresh" content="1">';
                    } else {
                        // الفيديو جاهز
                        $embedUrl = $result['embed_url'] ?? "https://app.heygen.com/embeds/" . ($result['video_id'] ?? '');
                        $msg = 'تم توليد الفيديو بنجاح! <a href="' . htmlspecialchars($embedUrl) . '" target="_blank" class="btn btn-sm btn-accent"><i class="fas fa-video"></i> مشاهدة</a>';
                    }
                } else {
                    $msg = 'فشل توليد الفيديو: ' . $result['error'];
                    $msgType = 'danger';
                }
            }
        } else {
            $msg = 'يرجى إدخال رابط يوتيوب أو طلب التوليد من PDF.';
            $msgType = 'warning';
        }
    }

    // ========== SCHOLAR ==========
    if ($genType === 'scholar') {        $scholarName = trim($_POST['scholar_name'] ?? '');
        if (empty($scholarName)) {
            $msg = 'يرجى إدخال اسم العالم.';
            $msgType = 'warning';
        } else {
            $prompt = "اكتب معلومات موجزة عن عالم النحو العربي «{$scholarName}».\n\nأجب بـ JSON:\n{\"name\":\"...\",\"era\":\"...\",\"short_bio\":\"...\",\"works\":\"...\"}";
            
            $result = callAI($prompt, $apiKey, $provider);
            if ($result['success']) {
                $cleanText = cleanJsonResponse($result['text']);
                $parsed = @json_decode($cleanText, true);
                
                if ($parsed && isset($parsed['name'])) {
                    $db->prepare("INSERT INTO scholars (name, era, short_bio, works) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE era=VALUES(era), short_bio=VALUES(short_bio), works=VALUES(works)")
                       ->execute([
                           $parsed['name'] ?? $scholarName, 
                           $parsed['era'] ?? '', 
                           $parsed['short_bio'] ?? '', 
                           $parsed['works'] ?? ''
                       ]);
                    $msg = 'تمت إضافة العالم: ' . clean($scholarName);
                } else {
                    $msg = 'فشل تحليل JSON.';
                    $msgType = 'warning';
                }
            } else {
                $msg = 'فشل الاستدعاء: ' . $result['error']; 
                $msgType = 'danger';
            }
        }
    }

    // ========== INFOGRAPHIC ==========
    if ($genType === 'infographic') {
        // خيار أ: رفع صورة من جهاز المستخدم
        if (isset($_FILES['infographic_file']) && $_FILES['infographic_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['infographic_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $msg     = 'صيغة الملف غير مدعومة. يرجى رفع JPG أو PNG أو WebP.';
                $msgType = 'danger';
            } else {
                $safeFileName = 'infographic_lesson_' . $lessonId . '_' . time() . '.' . $ext;
                $uploadPath   = UPLOAD_DIR . 'infografic/';

                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                $filePath = $uploadPath . $safeFileName;
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $infographicUrl = '/uploads/infografic/' . $safeFileName;
                    $db->prepare("UPDATE lessons SET infographic_url=? WHERE id=?")->execute([$infographicUrl, $lessonId]);
                    $msg = 'تم رفع الإنفوجرافيك بنجاح!';
                } else {
                    $msg     = 'فشل رفع الملف. يرجى المحاولة مرة أخرى.';
                    $msgType = 'danger';
                }
            }
        }
        // خيار ب: توليد إنفوجرافيك عبر Flux AI
        elseif (!empty($_POST['generate_infographic_from_pdf'])) {
            $pdfContent = '';

            if (!empty($lesson['pdf_url'])) {
                $pdfPath    = UPLOAD_DIR . 'pdfs/' . basename($lesson['pdf_url']);
                $pdfContent = extractPdfText($pdfPath);
            }

            if (empty($pdfContent)) {
                $msg     = 'لم يتم العثور على ملف PDF للدرس. يرجى رفع PDF أولاً.';
                $msgType = 'warning';
            } else {
                $fluxKey = $submittedKey ?: ($envKeys['flux'] ?? '');
                $result  = generateInfographicWithFlux($lesson['name'], $pdfContent, $fluxKey, $lessonId, $db);

                if ($result['success']) {
                    $msg = 'تم توليد الإنفوجرافيك بنجاح! <a href="' . htmlspecialchars($result['infographic_url']) . '" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-image"></i> عرض الإنفوجرافيك</a>';
                } else {
                    $msg     = 'فشل توليد الإنفوجرافيك: ' . $result['error'];
                    $msgType = 'danger';
                }
            }
        } else {
            $msg     = 'يرجى رفع صورة أو طلب التوليد من PDF.';
            $msgType = 'warning';
        }
    }
}

// ========== HELPER FUNCTIONS ==========

function cleanJsonResponse(string $text): string {
    $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
    $text = preg_replace('/\s*```$/m', '', $text);
    $text = trim($text);
    
    $firstBracket = min(
        strpos($text, '{') !== false ? strpos($text, '{') : PHP_INT_MAX,
        strpos($text, '[') !== false ? strpos($text, '[') : PHP_INT_MAX
    );
    
    if ($firstBracket !== PHP_INT_MAX && $firstBracket > 0) {
        $text = substr($text, $firstBracket);
    }
    
    $lastBracket = max(
        strrpos($text, '}') !== false ? strrpos($text, '}') : -1,
        strrpos($text, ']') !== false ? strrpos($text, ']') : -1
    );
    
    if ($lastBracket !== -1 && $lastBracket < strlen($text) - 1) {
        $text = substr($text, 0, $lastBracket + 1);
    }
    
    return trim($text);
}

function extractPdfText(string $filePath): string {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return '';
    }

    if (function_exists('shell_exec')) {
        $cmd = 'which pdftotext 2>/dev/null';
        if (!empty(shell_exec($cmd))) {
            $out = shell_exec('pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null');
            if (!empty(trim($out))) {
                return trim($out);
            }
        }
    }

    $raw = file_get_contents($filePath);
    if ($raw === false) return '';

    $text = '';
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams)) {
        foreach ($streams[1] as $stream) {
            $uncompressed = @gzuncompress($stream);
            $block = ($uncompressed !== false) ? $uncompressed : $stream;

            if (preg_match_all('/BT\s*(.*?)\s*ET/s', $block, $btBlocks)) {
                foreach ($btBlocks[1] as $bt) {
                    if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $bt, $tj)) {
                        $text .= implode(' ', $tj[1]) . ' ';
                    }
                }
            }
        }
    }

    return trim($text);
}

function generatePodcast(string $lessonName, string $content, string $apiKey, string $provider, int $lessonId, $db): array {
    // سنتحقق من المفتاح الصحيح لاحقاً حسب المزود
    
    // 1. توليد سكريبت حواري باستخدام Gemini (أفضل للعربية)
    $truncated = mb_substr($content, 0, 8000);
    
    $scriptPrompt = <<<PROMPT
أنت كاتب سيناريو بودكاست تعليمي. اصنع حواراً تعليمياً بسيطاً باللغة العربية الفصحى حول: «{$lessonName}».

**محتوى الدرس:**
{$truncated}

**المطلوب:**
حوار بين شخصين (أستاذة وطالب) - **التزم بمحتوى الدرس فقط**:
- **المتحدث 1 (أستاذة)**: صوت أنثوي، تشرح بوضوح وبساطة
- **المتحدث 2 (طالب)**: صوت ذكوري، يسأل أسئلة بسيطة

**المواصفات:**

1. **المدة:** 8-10 دقائق (1600-2000 كلمة فقط - مهم جداً!)

2. **الهيكل:**
   - **مقدمة** (دقيقة): ترحيب وتقديم الموضوع
   - **الشرح** (7 دقائق): 
     * اشرح المفاهيم الأساسية من الدرس فقط
     * مثال واحد تطبيقي (من شعر أو قصة قصيرة)
     * 4-5 أسئلة من الطالب
   - **خاتمة** (دقيقة): تلخيص ونصيحة واحدة

3. **الأسلوب:**
   - لغة بسيطة للمستوى المتوسط (ليست معقدة)
   - اشرح المصطلحات الصعبة
   - مثال واحد كافٍ لكل مفهوم
   - تجنب الإطالة والتكرار

4. **الأمثلة:**
   - مثال واحد فقط من الشعر أو قصة قصيرة
   - أو مثال من حياة الطالب اليومية
   - علامات الإعراب واضحة

5. **التنسيق - مهم جداً:**
   ⚠️ **لا تذكر أسماء المتحدثين!**
   - كل مداخلة في سطر منفصل
   - سطر فارغ بين كل مداخلة
   - ابدأ بالأستاذة
   - تناوب: أستاذة → طالب → أستاذة

**مثال التنسيق:**

السلام عليكم! اليوم سنتعلم عن...

وعليكم السلام! أنا متحمس لهذا الدرس...

رائع! لنبدأ...

**ملاحظات مهمة:**
- لا تتجاوز 2000 كلمة (حد صارم!)
- ركز على العناصر الأساسية في الدرس
- لا تضف معلومات خارج ملف الدرس
- مثال واحد يكفي لكل فكرة
- حوار مباشر بدون أسماء

ابدأ الآن:
PROMPT;
    
    // استخدام مفتاح Gemini لتوليد السكريبت
    $geminiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($geminiKey)) {
        return ['success' => false, 'error' => 'مفتاح Gemini مطلوب لتوليد السكريبت'];
    }
    
    $scriptResult = callAI($scriptPrompt, $geminiKey, 'gemini');
    if (!$scriptResult['success']) {
        return ['success' => false, 'error' => 'فشل توليد السكريبت: ' . $scriptResult['error']];
    }
    
    $script = $scriptResult['text'];
    
    // 2. تحويل لصوت حسب المزود المختار
    
    // ElevenLabs - صوتين مختلفين (أستاذة وطالب)
    if ($provider === 'elevenlabs') {
        // فصل الحوار إلى سطور
        $lines = array_filter(array_map('trim', explode("\n", $script)));
        
        // أصوات: أنثوي للأستاذة، ذكوري للطالب
        $voiceFemale = '21m00Tcm4TlvDq8ikWAM'; // Rachel - صوت أنثوي (العربية)
        $voiceMale = 'pNInz6obpgDQGcFmaJgB'; // Adam - صوت ذكوري (العربية)
        
        $audioSegments = [];
        $isFemale = true; // نبدأ بالأستاذة
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $voiceId = $isFemale ? $voiceFemale : $voiceMale;
            $url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";
            
            $payload = json_encode([
                'text' => $line,
                'model_id' => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                    'style' => 0.0,
                    'use_speaker_boost' => true
                ]
            ]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'xi-api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: audio/mpeg'
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            
            $segmentAudio = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $errorMsg = 'فشل توليد الصوت من ElevenLabs (HTTP ' . $httpCode . ')';
                if (!empty($curlError)) {
                    $errorMsg .= ': ' . $curlError;
                }
                $errorData = @json_decode($segmentAudio, true);
                if ($errorData && isset($errorData['detail'])) {
                    $errorMsg .= ' - ' . ($errorData['detail']['message'] ?? $errorData['detail']);
                }
                return ['success' => false, 'error' => $errorMsg];
            }
            
            $audioSegments[] = $segmentAudio;
            $isFemale = !$isFemale; // تبديل بين الأصوات
        }
        
        // دمج جميع المقاطع الصوتية
        $audioContent = implode('', $audioSegments);
        $ext = 'mp3';
    }
    // OpenAI TTS
    // OpenAI TTS - صوتان مختلفان (أستاذة وطالب)
    elseif ($provider === 'openai' || $provider === 'openai_tts') {
        $url = "https://api.openai.com/v1/audio/speech";
        
        // استخدام مفتاح OpenAI من config أو من الحقل
        $openaiKey = '';
        if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '' && OPENAI_API_KEY !== null) {
            $openaiKey = OPENAI_API_KEY;
        } elseif (!empty($apiKey)) {
            $openaiKey = $apiKey;
        }
        
        if (empty($openaiKey)) {
            return ['success' => false, 'error' => 'مفتاح OpenAI مطلوب. يرجى إضافة OPENAI_API_KEY في config/db.php'];
        }
        
        // فصل الحوار إلى سطور
        $lines = array_filter(array_map('trim', explode("\n", $script)));
        
        // أصوات: أنثوي للأستاذة، ذكوري للطالب
        $voiceFemale = 'nova';   // صوت أنثوي
        $voiceMale = 'onyx';     // صوت ذكوري
        
        $audioSegments = [];
        $isFemale = true; // نبدأ بالأستاذة
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $voiceId = $isFemale ? $voiceFemale : $voiceMale;
            
            $payload = json_encode([
                'model' => 'gpt-4o-mini-tts',
                'input' => $line,
                'voice' => $voiceId,
                'response_format' => 'mp3'
            ]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $openaiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            
            $segmentAudio = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $errorMsg = 'فشل توليد الصوت من OpenAI (HTTP ' . $httpCode . ')';
                if (!empty($curlError)) {
                    $errorMsg .= ': ' . $curlError;
                }
                $errorData = @json_decode($segmentAudio, true);
                if ($errorData && isset($errorData['error'])) {
                    $errorMsg .= ' - ' . ($errorData['error']['message'] ?? '');
                }
                return ['success' => false, 'error' => $errorMsg];
            }
            
            $audioSegments[] = $segmentAudio;
            $isFemale = !$isFemale; // تبديل بين الأصوات
        }
        
        // دمج جميع المقاطع الصوتية
        $audioContent = implode('', $audioSegments);
        $ext = 'mp3';
    }
    else {
        return ['success' => false, 'error' => 'مزود الصوت غير مدعوم. استخدم elevenlabs أو openai'];
    }
    
    // 3. حفظ الملف
    $safeFileName = 'podcast_lesson_' . $lessonId . '_' . time() . '.' . $ext;
    $uploadPath = UPLOAD_DIR . 'podcasts/';
    
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    $filePath = $uploadPath . $safeFileName;
    if (!file_put_contents($filePath, $audioContent)) {
        return ['success' => false, 'error' => 'فشل حفظ ملف الصوت'];
    }
    
    $podcastUrl = '/uploads/podcasts/' . $safeFileName;
    $db->prepare("UPDATE lessons SET podcast_url=? WHERE id=?")->execute([$podcastUrl, $lessonId]);
    
    return ['success' => true, 'podcast_url' => $podcastUrl];
}

function generateVideo(string $lessonName, string $content, string $apiKey, int $lessonId, $db): array {
    // استخدام مفتاح HeyGen المحدد
    $heygenKey = defined('HEYGEN_API_KEY') && !empty(HEYGEN_API_KEY) 
        ? HEYGEN_API_KEY 
        : $apiKey;
    
    if (empty($heygenKey)) {
        return ['success' => false, 'error' => 'مفتاح HeyGen مطلوب. يرجى إضافة HEYGEN_API_KEY في config/db.php'];
    }
    
    // 1. توليد سكريبت الفيديو بواسطة Gemini
    $truncated = mb_substr($content, 0, 5000);
    
    $scriptPrompt = <<<PROMPT
Task: Analyze the attached PDF content and create an educational video script in Arabic language.

**Content:**
{$truncated}

**Requirements:**
- Avatar: Use a professional Male Teacher avatar
- Setting: A classroom environment with a whiteboard
- Visuals: Include dynamic infographics, arrows, and bullet points on the screen
- Voice: Natural Arabic male voice with educational tone
- Content Structure:
  1. Introduction (30 seconds): Welcome and overview
  2. Key Points (3-4 minutes): Step-by-step lesson breakdown
  3. Summary (30 seconds): Quick recap and closing

**Output Format:**
Write a clear, engaging Arabic script for the teacher to speak. Keep it conversational and educational.
Duration: 3-5 minutes total.

Begin the script now:
PROMPT;
    
    $geminiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($geminiKey)) {
        return ['success' => false, 'error' => 'مفتاح Gemini مطلوب لتوليد السكريبت'];
    }
    
    $scriptResult = callAI($scriptPrompt, $geminiKey, 'gemini');
    if (!$scriptResult['success']) {
        return ['success' => false, 'error' => 'فشل توليد السكريبت: ' . $scriptResult['error']];
    }
    
    $script = $scriptResult['text'];
    
    // 2. استدعاء HeyGen v3 Video Agent API
    $url = "https://api.heygen.com/v3/video-agents";
    
    // تقصير السكريبت إذا كان طويلاً جداً
    $shortScript = mb_substr($script, 0, 800); // حد أقصى 800 حرف
    
    // برومبت مُبسّط للغاية
    $prompt = "A 3-minute Arabic educational video about {$lessonName}. Script: {$shortScript}";
    
    // استخدام Video Agent
    $payload = [
        'prompt' => $prompt
    ];
    
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_HTTPHEADER => [
            'X-Api-Key: ' . $heygenKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        $errorMsg = 'فشل الاتصال بـ HeyGen (HTTP ' . $httpCode . ')';
        if (!empty($curlError)) {
            $errorMsg .= ': ' . $curlError;
        }
        $errorData = @json_decode($response, true);
        if ($errorData) {
            if (isset($errorData['message'])) {
                $errorMsg .= ' - ' . $errorData['message'];
            }
            if (isset($errorData['error'])) {
                $errorMsg .= ' - ' . (is_array($errorData['error']) ? json_encode($errorData['error']) : $errorData['error']);
            }
        }
        return ['success' => false, 'error' => $errorMsg];
    }
    
    $data = json_decode($response, true);
    // Video Agent يعيد video_id في data
    $videoId = $data['data']['video_id'] ?? $data['video_id'] ?? '';
    
    if (empty($videoId)) {
        return ['success' => false, 'error' => 'لم يتم إرجاع معرف الفيديو من HeyGen'];
    }
    
    // حفظ video_id في قاعدة البيانات مباشرة (بدون انتظار)
    // سنستخدم JSON لتخزين البيانات
    $videoData = json_encode([
        'video_id' => $videoId,
        'status' => 'processing',
        'provider' => 'heygen',
        'created_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
    $db->prepare("UPDATE lessons SET video_url=? WHERE id=?")->execute([$videoData, $lessonId]);
    
    return [
        'success' => true, 
        'video_id' => $videoId,
        'status' => 'processing',
        'message' => 'بدأ توليد الفيديو بنجاح. سيتم إشعارك عند الانتهاء.'
    ];
}

function pollHeyGenVideo(string $videoId, string $apiKey): ?string {
    // استخدام v3 API endpoint
    $url = "https://api.heygen.com/v3/videos/" . urlencode($videoId);
    $maxAttempts = 30;  // 30 × 10 ثانية = 5 دقائق كحد أقصى
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        sleep(10);  // انتظار 10 ثوانٍ بين كل محاولة
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            continue;  // حاول مرة أخرى
        }
        
        $data = json_decode($response, true);
        $status = $data['data']['status'] ?? $data['status'] ?? '';
        
        if ($status === 'completed') {
            // في v3، الفيديو قد يكون في data.video_url أو data.url
            return $data['data']['video_url'] ?? $data['data']['url'] ?? $data['url'] ?? null;
        } elseif ($status === 'failed' || $status === 'error') {
            return null;  // فشل التوليد
        }
        // إذا كان processing أو pending، استمر في الانتظار
    }
    
    return null;  // انتهت المحاولات بدون نجاح
}

function generateGammaPresentation(string $lessonName, string $content, string $apiKey, int $lessonId, $db): array {
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'لم يتم تعيين مفتاح Gamma'];
    }
    
    $url = "https://public-api.gamma.app/v1.0/generations";
    
    $inputText = "الدرس: {$lessonName}\n\nالمحتوى:\n{$content}";
    
    $payload = json_encode([
        'inputText' => $inputText,
        'textMode' => 'generate',
        'format' => 'presentation',
        'numCards' => 10,
        'exportAs' => 'pdf'
    ], JSON_UNESCAPED_UNICODE);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . $apiKey],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        return ['success' => false, 'error' => "خطأ من Gamma (HTTP $httpCode)"];
    }
    
    $data = json_decode($resp, true);
    $generationId = $data['generationId'] ?? '';
    
    if (empty($generationId)) {
        return ['success' => false, 'error' => 'لم يتم إرجاع معرف التوليد'];
    }
    
    // Polling
    $pollUrl = "https://public-api.gamma.app/v1.0/generations/{$generationId}";
    
    for ($attempt = 0; $attempt < 30; $attempt++) {
        sleep(5);
        
        $ch = curl_init($pollUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . $apiKey]
        ]);
        
        $pollResp = curl_exec($ch);
        $pollCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($pollCode !== 200) continue;
        
        $pollData = json_decode($pollResp, true);
        $status = $pollData['status'] ?? '';
        
        if ($status === 'completed') {
            $gammaUrl = $pollData['gammaUrl'] ?? '';
            $exportUrl = $pollData['exportUrl'] ?? '';
            
            // حفظ JSON metadata
            $gammaData = json_encode([
                'type' => 'gamma',
                'generation_id' => $generationId,
                'gamma_url' => $gammaUrl,
                'export_url' => $exportUrl
            ], JSON_UNESCAPED_UNICODE);
            $db->prepare("UPDATE lessons SET presentation_html=? WHERE id=?")->execute([$gammaData, $lessonId]);
            
            // تحميل PDF محلياً
            $localPdfPath = '';
            $localPdfUrl = '';
            
            if (!empty($exportUrl)) {
                try {
                    $pdfContent = @file_get_contents($exportUrl);
                    
                    if ($pdfContent !== false) {
                        $safeFileName = 'gamma_presentation_lesson_' . $lessonId . '_' . time() . '.pdf';
                        $localPdfPath = UPLOAD_DIR . 'pdfs/' . $safeFileName;
                        
                        if (file_put_contents($localPdfPath, $pdfContent)) {
                            $localPdfUrl = '/uploads/pdfs/' . $safeFileName;
                            $db->prepare("UPDATE lessons SET presentation_pdf=? WHERE id=?")->execute([$localPdfUrl, $lessonId]);
                        }
                    }
                } catch (Exception $e) {
                    // تجاهل
                }
            }
            
            return [
                'success' => true,
                'generation_id' => $generationId,
                'gamma_url' => $gammaUrl,
                'export_url' => $exportUrl,
                'local_pdf_path' => $localPdfPath,
                'local_pdf_url' => $localPdfUrl
            ];
        } elseif ($status === 'failed') {
            return ['success' => false, 'error' => 'فشل Gamma'];
        }
    }
    
    return ['success' => false, 'error' => 'انتهت مهلة الانتظار'];
}

function callAI(string $prompt, string $apiKey, string $provider): array {
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'لم يتم تعيين مفتاح API'];
    }

    // Gemini
    if ($provider === 'gemini') {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . urlencode($apiKey);
        
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['maxOutputTokens' => 8192, 'temperature' => 0.2]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120
        ]);
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $data = json_decode($resp, true);
            $apiErr = $data['error']['message'] ?? 'خطأ غير معروف';
            return ['success' => false, 'error' => "خطأ من Gemini (HTTP $httpCode): " . mb_substr($apiErr, 0, 300)];
        }
        
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        if (empty($text)) {
            return ['success' => false, 'error' => 'Gemini لم يعيد أي نص'];
        }
        
        return ['success' => true, 'text' => trim($text)];
    }

    // OpenAI
    if ($provider === 'openai') {
        $url = "https://api.openai.com/v1/chat/completions";
        $payload = json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 8192,
            'temperature' => 0.2
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT => 120
        ]);
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $data = json_decode($resp, true);
            $apiErr = $data['error']['message'] ?? 'خطأ غير معروف';
            return ['success' => false, 'error' => "خطأ من OpenAI (HTTP $httpCode): " . mb_substr($apiErr, 0, 300)];
        }
        
        $data = json_decode($resp, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        
        if (empty($text)) {
            return ['success' => false, 'error' => 'OpenAI لم يعيد أي نص'];
        }
        
        return ['success' => true, 'text' => trim($text)];
    }

    // Claude
    if ($provider === 'claude') {
        $url = "https://api.anthropic.com/v1/messages";
        
        $payload = json_encode([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 8192,
            'temperature' => 0.2,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 120
        ]);
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $data = json_decode($resp, true);
            $errMsg = $data['error']['message'] ?? 'خطأ غير معروف';
            return ['success' => false, 'error' => "خطأ من Claude (HTTP $httpCode): " . mb_substr($errMsg, 0, 300)];
        }
        
        $data = json_decode($resp, true);
        $text = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if ($block['type'] === 'text') {
                    $text .= $block['text'];
                }
            }
        }
        
        if (empty($text)) {
            return ['success' => false, 'error' => 'Claude لم يعيد أي نص'];
        }
        
        return ['success' => true, 'text' => trim($text)];
    }

    return ['success' => false, 'error' => 'مزود غير معروف'];
}

// ========== FLUX INFOGRAPHIC HELPERS ==========

function generateInfographicWithFlux(string $lessonName, string $content, string $apiKey, int $lessonId, $db): array {
    $fluxKey = !empty($apiKey) ? $apiKey : (defined('FLUX_API_KEY') ? FLUX_API_KEY : '');

    if (empty($fluxKey)) {
        return ['success' => false, 'error' => 'مفتاح Flux API مطلوب. يرجى إضافة FLUX_API_KEY في إعدادات البيئة أو إدخاله يدوياً.'];
    }

    $uploadPath = UPLOAD_DIR . 'infografic/';
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $truncated = mb_substr($content, 0, 2000); // limit prompt size

    $prompt = "Educational Arabic infographic for the lesson «{$lessonName}». "
        . "Create a clear visual tree/hierarchical diagram showing: "
        . "main lesson title, key types with Arabic examples, sub-categories, patterns. "
        . "Use comfortable colors (green, gold, white background), large clear Arabic font, "
        . "landscape 1280x720 format. "
        . "Content: {$truncated}. "
        . "Professional educational design that helps students understand the lesson from a single image.";

    // Flux Kontext API – text-to-image endpoint
    $url = 'https://api.fluxapi.ai/api/v1/flux/kontext/generate';

    $payload = json_encode([
        'prompt'            => $prompt,
        'model'             => 'flux-kontext-pro',
        'aspectRatio'       => '16:9',
        'outputFormat'      => 'png',
        'enableTranslation' => true,
        'promptUpsampling'  => false,
        'safetyTolerance'   => 2,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $fluxKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 120,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curlError)) {
        return ['success' => false, 'error' => 'فشل الاتصال بـ Flux API: ' . $curlError];
    }

    if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {
        $errorData = @json_decode($response, true);
        // Build a useful message: prefer structured API error, fall back to raw body
        $errorMsg = $errorData['message']
            ?? ($errorData['error']['message'] ?? ($errorData['error'] ?? null));
        if (is_array($errorMsg)) {
            $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
        }
        if (empty($errorMsg)) {
            // No structured error – show raw response so the developer can diagnose
            $errorMsg = !empty($response) ? mb_substr($response, 0, 300) : 'لا يوجد محتوى في الاستجابة';
        }
        return ['success' => false, 'error' => 'خطأ من Flux API (HTTP ' . $httpCode . '): ' . (string)$errorMsg];
    }

    $data = @json_decode($response, true);
    if ($data === null) {
        return ['success' => false, 'error' => 'فشل تحليل استجابة Flux API.'];
    }

    // ---- الحالة 1: رابط صورة مباشر ----
    $imageUrl = _fluxFirstNonEmptyString($data, [
        'imageUrl',
        'image_url',
        'url',
        'images.0.url',
        'data.imageUrl',
        'data.image_url',
        'data.url',
        'data.images.0.url',
        'result.imageUrl',
        'result.image_url',
        'result.url',
        'result.images.0.url',
        'output.imageUrl',
        'output.image_url',
        'output.url',
    ]);
    if (!empty($imageUrl)) {
        return _downloadAndSaveFluxImage($imageUrl, $lessonId, $db, $uploadPath);
    }

    // ---- الحالة 2: صورة base64 ----
    $base64 = _fluxFirstNonEmptyString($data, [
        'image',
        'base64',
        'b64_json',
        'imageBase64',
        'images.0.b64_json',
        'data.image',
        'data.base64',
        'data.b64_json',
        'data.imageBase64',
        'data.images.0.b64_json',
        'result.image',
        'result.base64',
        'result.b64_json',
        'result.imageBase64',
        'result.images.0.b64_json',
        'output.image',
        'output.base64',
        'output.b64_json',
    ]);
    if (!empty($base64)) {
        return _saveBase64FluxImage($base64, $lessonId, $db, $uploadPath);
    }

    // ---- الحالة 3: task/polling (استجابة غير متزامنة) ----
    $taskId = _fluxFirstNonEmptyString($data, [
        'task_id',
        'taskId',
        'request_id',
        'requestId',
        'job_id',
        'jobId',
        'id',
        'data.task_id',
        'data.taskId',
        'data.request_id',
        'data.requestId',
        'data.job_id',
        'data.jobId',
        'data.id',
        'result.task_id',
        'result.taskId',
        'result.request_id',
        'result.requestId',
        'result.job_id',
        'result.jobId',
        'result.id',
    ]);
    if (!empty($taskId)) {
        $pollingUrl = _fluxFirstNonEmptyString($data, [
            'polling_url',
            'pollingUrl',
            'status_url',
            'statusUrl',
            'data.polling_url',
            'data.pollingUrl',
            'data.status_url',
            'data.statusUrl',
            'result.polling_url',
            'result.pollingUrl',
            'result.status_url',
            'result.statusUrl',
        ]);
        if (empty($pollingUrl)) {
            $pollingUrl = 'https://api.fluxapi.ai/api/v1/status/' . $taskId;
        }
        return _pollAndSaveFluxImage($taskId, $pollingUrl, $fluxKey, $lessonId, $db, $uploadPath);
    }

    $rawPreview = mb_substr($response, 0, 500);
    $keys       = implode(', ', array_keys($data));
    return ['success' => false, 'error' => 'لم تُعد Flux API أي صورة. مفاتيح الاستجابة: [' . $keys . ']. المعاينة: ' . $rawPreview];
}

function _fluxGetByPath($data, string $path) {
    if ($path === '') {
        return null;
    }
    $parts = explode('.', $path);
    $cur   = $data;
    foreach ($parts as $part) {
        if (is_array($cur) && array_key_exists($part, $cur)) {
            $cur = $cur[$part];
            continue;
        }
        if (is_array($cur) && ctype_digit($part)) {
            $idx = (int)$part;
            if (array_key_exists($idx, $cur)) {
                $cur = $cur[$idx];
                continue;
            }
        }
        return null;
    }
    return $cur;
}

function _fluxFirstNonEmptyString(array $data, array $paths): string {
    foreach ($paths as $path) {
        $val = _fluxGetByPath($data, $path);
        if (is_string($val) && trim($val) !== '') {
            return trim($val);
        }
    }
    return '';
}

function _pollAndSaveFluxImage(string $taskId, string $pollingUrl, string $apiKey, int $lessonId, $db, string $uploadPath): array {
    $maxAttempts   = 20; // 20 attempts × 6 s = 120 s max wait
    $pollIntervalS = 6;  // seconds between each status check

    for ($i = 0; $i < $maxAttempts; $i++) {
        sleep($pollIntervalS);

        $ch = curl_init($pollingUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $pollResp = curl_exec($ch);
        $pollCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($pollCode !== 200 || empty($pollResp)) {
            continue;
        }

        $pollData = @json_decode($pollResp, true);
        if (!is_array($pollData)) {
            continue;
        }

        $imageUrl = _fluxFirstNonEmptyString($pollData, [
            'imageUrl',
            'image_url',
            'url',
            'images.0.url',
            'data.imageUrl',
            'data.image_url',
            'data.url',
            'data.images.0.url',
            'result.imageUrl',
            'result.image_url',
            'result.url',
            'result.images.0.url',
            'output.imageUrl',
            'output.image_url',
            'output.url',
        ]);

        if (!empty($imageUrl)) {
            return _downloadAndSaveFluxImage($imageUrl, $lessonId, $db, $uploadPath);
        }

        $base64 = _fluxFirstNonEmptyString($pollData, [
            'image',
            'base64',
            'b64_json',
            'imageBase64',
            'images.0.b64_json',
            'data.image',
            'data.base64',
            'data.b64_json',
            'data.imageBase64',
            'data.images.0.b64_json',
            'result.image',
            'result.base64',
            'result.b64_json',
            'result.imageBase64',
            'result.images.0.b64_json',
            'output.image',
            'output.base64',
            'output.b64_json',
        ]);
        if (!empty($base64)) {
            return _saveBase64FluxImage($base64, $lessonId, $db, $uploadPath);
        }

        $status = strtolower((string)_fluxFirstNonEmptyString($pollData, ['status', 'state', 'data.status', 'data.state', 'result.status', 'result.state']));
        if (in_array($status, ['failed', 'error', 'cancelled'], true)) {
            $errMsg = $pollData['error'] ?? ($pollData['message'] ?? 'فشل توليد الصورة.');
            return ['success' => false, 'error' => (string)$errMsg];
        }
        // processing – استمر في الانتظار
    }

    return ['success' => false, 'error' => 'انتهت مهلة الانتظار. يرجى المحاولة مرة أخرى.'];
}

function _downloadAndSaveFluxImage(string $imageUrl, int $lessonId, $db, string $uploadPath): array {
    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $imageContent = curl_exec($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$imageContent || $httpCode !== 200) {
        return ['success' => false, 'error' => 'فشل تحميل الصورة من الرابط المُعاد (HTTP ' . $httpCode . ').'];
    }

    return _writeFluxImageToDisk($imageContent, $lessonId, $db, $uploadPath);
}

function _saveBase64FluxImage(string $base64, int $lessonId, $db, string $uploadPath): array {
    if (str_contains($base64, ',')) {
        $base64 = explode(',', $base64, 2)[1];
    }
    $imageContent = base64_decode($base64, true);
    if ($imageContent === false) {
        return ['success' => false, 'error' => 'فشل فك ترميز الصورة (base64).'];
    }
    return _writeFluxImageToDisk($imageContent, $lessonId, $db, $uploadPath);
}

function _writeFluxImageToDisk(string $imageContent, int $lessonId, $db, string $uploadPath): array {
    $safeFileName = 'infographic_lesson_' . $lessonId . '_' . time() . '.png';
    $filePath     = $uploadPath . $safeFileName;

    if (!file_put_contents($filePath, $imageContent)) {
        return ['success' => false, 'error' => 'فشل حفظ ملف الصورة على السيرفر.'];
    }

    $infographicUrl = '/uploads/infografic/' . $safeFileName;
    $db->prepare("UPDATE lessons SET infographic_url=? WHERE id=?")->execute([$infographicUrl, $lessonId]);

    return ['success' => true, 'infographic_url' => $infographicUrl];
}

$configuredProviders = array_keys(array_filter($envKeys));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>الذكاء الاصطناعي – <?= clean($lesson['name']) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
  .presentation-iframe-wrapper {
    width: 100%;
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: #f5f5f5;
    border: 2px solid var(--border);
  }
  .presentation-iframe {
    width: 100%;
    min-height: 600px;
    border: none;
    display: block;
  }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand"><button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button><span>�‍�</span><span>لوحة الأستاذ</span></div>
  <ul class="navbar-nav"><li><a href="/api/auth.php?action=logout_teacher" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li></ul>
</nav>
<aside class="sidebar">
  <a href="/teacher/dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> الرئيسية</a>
  <a href="/teacher/students.php"  class="sidebar-link"><i class="fas fa-users"></i> الطلاب</a>
  <a href="/teacher/courses.php"   class="sidebar-link"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"   class="sidebar-link active"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"  class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
</aside>
<main class="main-content">
  <div style="margin-bottom:.75rem;font-size:.88rem;color:var(--muted);">
    <a href="/teacher/lessons.php">الدروس</a> / <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>"><?= clean($lesson['name']) ?></a> / <strong>الذكاء الاصطناعي</strong>
  </div>
  <h2 style="margin-bottom:1.5rem;"><i class="fas fa-robot" style="color:var(--accent);"></i> توليد المحتوى بالذكاء الاصطناعي</h2>
  <p style="color:var(--muted);margin-bottom:1.5rem;">الدرس: <strong><?= clean($lesson['name']) ?></strong> – <?= clean($lesson['course_name']) ?></p>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><i class="fas fa-info-circle"></i> <?= $msg ?></div>
  <?php endif; ?>

  <!-- Provider selector -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-robot"></i> إعدادات مزوّد الذكاء الاصطناعي</div></div>
    <div style="display:grid;grid-template-columns:auto 1fr;gap:1rem;align-items:center;padding:1rem;">
      <label class="form-label" style="margin:0;">المزوّد</label>
      <select id="providerSel" class="form-control" style="width:auto;max-width:400px;">
        <optgroup label="نماذج النصوص والعروض">
          <option value="gemini" <?= in_array('gemini', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            Google Gemini 2.5 Flash-Lite <?= in_array('gemini', $configuredProviders) ? '✓' : '' ?>
          </option>
          <option value="openai" <?= in_array('openai', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            OpenAI GPT-4o-mini <?= in_array('openai', $configuredProviders) ? '✓' : '' ?>
          </option>
          <option value="claude" <?= in_array('claude', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            Anthropic Claude Haiku 4.5 <?= in_array('claude', $configuredProviders) ? '✓' : '' ?>
          </option>
          <option value="gamma" <?= in_array('gamma', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            Gamma (عروض احترافية) <?= in_array('gamma', $configuredProviders) ? '✓' : '' ?>
          </option>
        </optgroup>
        <optgroup label="توليد الصوت">
          <option value="elevenlabs" <?= in_array('elevenlabs', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            ElevenLabs (أفضل جودة عربية) <?= in_array('elevenlabs', $configuredProviders) ? '✓' : '' ?>
          </option>
          <option value="openai_tts" <?= in_array('openai', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            OpenAI TTS (صوت alloy) <?= in_array('openai', $configuredProviders) ? '✓' : '' ?>
          </option>
        </optgroup>
        <optgroup label="توليد الفيديو">
          <option value="heygen" <?= in_array('heygen', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            HeyGen (مقدم افتراضي) <?= in_array('heygen', $configuredProviders) ? '✓' : '' ?>
          </option>
        </optgroup>
        <optgroup label="توليد الصور">
          <option value="flux" <?= in_array('flux', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            Flux Kontext (إنفوجرافيك) <?= in_array('flux', $configuredProviders) ? '✓' : '' ?>
          </option>
        </optgroup>
      </select>

      <label class="form-label" style="margin:0;">مفتاح API (اختياري)</label>
      <div>
        <input type="password" id="apiKeyOverride" class="form-control"
               placeholder="اتركه فارغاً لاستخدام المفتاح المحفوظ"
               autocomplete="new-password" style="max-width:480px;">
        <small id="keyStatus" style="display:block;margin-top:.35rem;color:var(--muted);"></small>
      </div>
    </div>
  </div>

  <!-- Generation cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;">

    <!-- Questions -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-question-circle"></i> توليد الأسئلة</div>
      </div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">يولد 30 سؤال من PDF الدرس. يمكنك تعديلها لاحقاً.</p>
      <form method="POST" onsubmit="return validateQuestionsForm()">
        <input type="hidden" name="gen_type" value="questions">
        <input type="hidden" name="provider" id="qProvider">
        <input type="hidden" name="api_key_override" id="qApiKey">
        <button type="submit" class="btn btn-primary btn-block" onclick="injectFormVars(this.form)">
          <i class="fas fa-magic"></i> توليد الأسئلة
        </button>
      </form>
      <?php
      // عرض عدد الأسئلة الحالية
      $qCount = $db->prepare("SELECT COUNT(*) FROM questions WHERE lesson_id = ?");
      $qCount->execute([$lessonId]);
      $totalQuestions = $qCount->fetchColumn();
      if ($totalQuestions > 0):
      ?>
      <div style="margin-top:1rem;padding:.75rem;background:#E8F5E9;border-radius:var(--radius-sm);text-align:center;">
        <strong style="color:#2E7D32;"><?= $totalQuestions ?></strong> سؤال موجود
        <a href="/teacher/edit_questions.php?lesson_id=<?= $lessonId ?>" class="btn btn-sm btn-primary" style="margin-right:.5rem;">
          <i class="fas fa-edit"></i> تعديل
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Presentation -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-file-powerpoint" style="color:#E53935;"></i> العرض التقديمي</div></div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">Gamma: عروض احترافية | AI الآخر: HTML/PDF</p>
      <form method="POST" enctype="multipart/form-data" onsubmit="return validatePresentationForm()">
        <input type="hidden" name="gen_type" value="presentation">
        <input type="hidden" name="provider" id="pProvider">
        <input type="hidden" name="api_key_override" id="pApiKey">
        
        <div class="form-group">
          <label class="form-label"><i class="fas fa-file-pdf"></i> رفع PDF (اختياري)</label>
          <input type="file" name="presentation_pdf" id="presentationPdf" class="form-control" accept=".pdf">
        </div>
        
        <div style="text-align:center;margin:.5rem 0;color:var(--muted);">أو</div>
        
        <div class="form-group">
          <label class="form-label"><i class="fas fa-align-right"></i> ملخص المحتوى</label>
          <textarea name="content_summary" id="contentSummary" class="form-control" rows="3"></textarea>
        </div>
        
        <button type="submit" class="btn btn-danger btn-block" onclick="injectFormVars(this.form)">
          <i class="fas fa-magic"></i> توليد العرض
        </button>
      </form>
    </div>

    <!-- Podcast -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-podcast" style="color:var(--info);"></i> البودكاست الصوتي</div></div>
      
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="gen_type" value="podcast">
        <input type="hidden" name="provider" id="podProvider">
        <input type="hidden" name="api_key_override" id="podApiKey">
        
        <div class="form-group">
          <label class="form-label"><i class="fas fa-link"></i> رابط مباشر (MP3/M4A)</label>
          <input type="url" name="podcast_url_direct" class="form-control" placeholder="https://...">
          <small style="color:var(--muted);display:block;margin-top:.25rem;">أو رفع ملف:</small>
        </div>
        
        <div class="form-group">
          <input type="file" name="podcast_file" class="form-control" accept=".mp3,.m4a,.wav,.ogg">
        </div>
        
        <button type="submit" class="btn btn-info btn-block" onclick="injectFormVars(this.form)">
          <i class="fas fa-save"></i> حفظ البودكاست
        </button>
        
        <button type="submit" name="generate_podcast_from_pdf" value="1" class="btn btn-outline btn-block" style="margin-top:.5rem;" onclick="injectFormVars(this.form)">
          <i class="fas fa-magic"></i> توليد من PDF الدرس
        </button>
        
        <div style="margin-top:1rem;padding:.5rem;background:#f8f9fa;border-radius:4px;font-size:.75rem;color:#6c757d;text-align:center;">
          <i class="fas fa-info-circle" style="font-size:.7rem;"></i> 
          يتم توليد السكريبت بـ Gemini تلقائياً، ثم تحويله لصوت بالمزود المختار أعلاه (ElevenLabs أو OpenAI TTS)
        </div>
      </form>
    </div>

    <!-- Video -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-video" style="color:var(--accent);"></i> الفيديو التعليمي</div></div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">رابط يوتيوب | توليد من PDF (HeyGen)</p>
      
      <!-- حالة التوليد -->
      <?php
      $videoData = @json_decode($lesson['video_url'] ?? '', true);
      $isProcessing = $videoData && ($videoData['status'] ?? '') === 'processing';
      ?>
      
      <form method="POST" id="videoForm">
        <input type="hidden" name="gen_type" value="video">
        <input type="hidden" name="provider" value="heygen">
        <input type="hidden" name="api_key_override" id="vidApiKey">
        
        <div class="form-group">
          <label class="form-label"><i class="fas fa-youtube"></i> رابط يوتيوب</label>
          <input type="url" name="video_url_direct" class="form-control" placeholder="https://youtube.com/...">
        </div>
        
        <button type="submit" class="btn btn-accent btn-block" onclick="document.getElementById('vidApiKey').value = document.getElementById('apiKeyOverride').value.trim();">
          <i class="fas fa-save"></i> حفظ الفيديو
        </button>
        
        <button type="submit" name="generate_video_from_pdf" value="1" id="generateVideoBtn" class="btn btn-outline btn-block" style="margin-top:.5rem;" onclick="document.getElementById('vidApiKey').value = document.getElementById('apiKeyOverride').value.trim();">
          <?php if ($isProcessing): ?>
            <span class="spinner" style="display:inline-block;width:16px;height:16px;border:3px solid #4CAF50;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin-left:.4rem;"></span>
            جارٍ المعالجة...
          <?php else: ?>
            <i class="fas fa-magic"></i> توليد من PDF الدرس (HeyGen)
          <?php endif; ?>
        </button>
        <small style="color:var(--muted);display:block;margin-top:.25rem;text-align:center;">
          <?php if ($isProcessing): ?>
            قد يستغرق توليد الفيديو 15-20 دقيقة
          <?php else: ?>
            يحتاج مفتاح HeyGen API • الدقة: 720p
          <?php endif; ?>
        </small>
      </form>
    </div>

    <!-- Scholar -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-scroll"></i> إضافة عالم نحو</div></div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">اكتب اسم العالم وسيجلب الذكاء معلوماته.</p>
      <form method="POST" onsubmit="return validateScholarForm()">
        <input type="hidden" name="gen_type" value="scholar">
        <input type="hidden" name="provider" id="sProvider">
        <input type="hidden" name="api_key_override" id="sApiKey">
        <div class="form-group">
          <label class="form-label">اسم العالم</label>
          <input type="text" name="scholar_name" id="scholarName" class="form-control" placeholder="مثال: سيبويه" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block" onclick="injectFormVars(this.form)">
          <i class="fas fa-magic"></i> جلب المعلومات
        </button>
      </form>
    </div>

    <!-- Infographic -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-project-diagram" style="color:#8E24AA;"></i> المخطط البصري (Infographic)</div></div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">رفع صورة | توليد إنفوجرافيك تعليمي من PDF (Flux AI)</p>

      <form method="POST" enctype="multipart/form-data" id="infographicForm">
        <input type="hidden" name="gen_type" value="infographic">
        <input type="hidden" name="provider" id="infProvider">
        <input type="hidden" name="api_key_override" id="infApiKey">

        <div class="form-group">
          <label class="form-label"><i class="fas fa-image"></i> رفع صورة (JPG / PNG / WebP)</label>
          <input type="file" name="infographic_file" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        </div>

        <button type="submit" class="btn btn-block" style="background:#8E24AA;color:#fff;" onclick="injectFormVars(this.form)">
          <i class="fas fa-save"></i> حفظ الصورة المرفوعة
        </button>

        <button type="submit" name="generate_infographic_from_pdf" value="1"
                class="btn btn-outline btn-block" style="margin-top:.5rem;"
                onclick="injectFormVars(this.form)">
          <i class="fas fa-magic"></i> توليد من PDF الدرس (Flux AI)
        </button>

        <div style="margin-top:.75rem;padding:.5rem;background:#f8f9fa;border-radius:4px;font-size:.75rem;color:#6c757d;text-align:center;">
          <i class="fas fa-info-circle"></i>
          المقاس المستهدف: 1280×720 بكسل • يتطلب مفتاح Flux API
        </div>
      </form>

      <?php if (!empty($lesson['infographic_url'])): ?>
      <div style="margin-top:1rem;text-align:center;">
        <img src="<?= htmlspecialchars($lesson['infographic_url']) ?>"
             alt="الإنفوجرافيك الحالي"
             style="max-width:100%;border-radius:var(--radius-sm);border:2px solid var(--border);cursor:pointer;"
             onclick="window.open(this.src,'_blank')">
        <div style="margin-top:.5rem;">
          <a href="<?= htmlspecialchars($lesson['infographic_url']) ?>" target="_blank" class="btn btn-sm btn-primary">
            <i class="fas fa-external-link-alt"></i> فتح في تبويب جديد
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <div style="margin-top:1.5rem;text-align:center;">
    <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>" class="btn btn-outline">
      <i class="fas fa-arrow-right"></i> عودة إلى أسئلة الدرس
    </a>
  </div>
</main>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';

const CONFIGURED = <?= json_encode($configuredProviders, JSON_UNESCAPED_UNICODE) ?>;

function injectFormVars(form) {
  const provider = document.getElementById('providerSel').value;
  const key = document.getElementById('apiKeyOverride').value.trim();
  const providerInput = form.querySelector('[name="provider"]');
  const keyInput = form.querySelector('[name="api_key_override"]');
  if (providerInput) providerInput.value = provider;
  if (keyInput) keyInput.value = key;
}

function validateQuestionsForm() {
  const provider = document.getElementById('providerSel').value;
  if (provider === 'gamma') {
    alert('Gamma للعروض فقط');
    return false;
  }
  return true;
}

function validatePresentationForm() {
  const hasFile = document.getElementById('presentationPdf').files.length > 0;
  const hasText = document.getElementById('contentSummary').value.trim().length > 0;
  if (!hasFile && !hasText) {
    alert('يرجى رفع PDF أو إدخال محتوى');
    return false;
  }
  return true;
}

function validateScholarForm() {
  const provider = document.getElementById('providerSel').value;
  if (provider === 'gamma') {
    alert('Gamma للعروض فقط');
    return false;
  }
  return true;
}

document.querySelectorAll('form').forEach(f => {
  f.addEventListener('submit', function() {
    const btn = this.querySelector('[type="submit"]');
    if (btn && !btn.disabled) { 
      btn.disabled = true; 
      const originalText = btn.innerHTML;
      btn.innerHTML = '<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin-left:.4rem;"></span> جارٍ المعالجة…';
      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }, 180000);
    }
  });
});

// التحقق من حالة الفيديو التلقائي
const lessonId = <?= $lessonId ?>;

function checkVideoStatus() {
  fetch(`?lesson_id=${lessonId}&check_video_status=1`)
    .then(r => r.json())
    .then(data => {
      if (data.status === 'processing') {
        // لا يزال قيد المعالجة - تحقق مرة أخرى بعد 15 ثانية
        setTimeout(checkVideoStatus, 15000);
      } else if (data.status === 'completed') {
        // اكتمل - أعد تحميل الصفحة لعرض النتيجة
        location.reload();
      } else if (data.status === 'failed') {
        // فشل - أعد تحميل الصفحة لعرض الخطأ
        location.reload();
      }
    })
    .catch(err => {
      // حاول مرة أخرى بعد 15 ثانية
      setTimeout(checkVideoStatus, 15000);
    });
}

// بدء التحقق التلقائي إذا كان الفيديو قيد المعالجة
<?php if ($isProcessing): ?>
checkVideoStatus();
<?php endif; ?>
</script>
<style>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
</body>
</html>

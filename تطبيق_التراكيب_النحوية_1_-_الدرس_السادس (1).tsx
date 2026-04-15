import { useState, useRef, useEffect } from "react";

const F = {fontFamily:"'Segoe UI',Tahoma,Arial,sans-serif"};

const bgStyle = {
  minHeight:"100vh",
  background:"linear-gradient(160deg,#0f1f3d 0%,#1a3a5c 40%,#0d2847 70%,#162040 100%)",
  position:"relative",
  ...F
};
const overlay = {display:"none"};
const rel = {position:"relative",zIndex:1};

const gW = (extra={}) => ({background:"rgba(255,255,255,0.96)",border:"1.5px solid rgba(30,58,95,0.15)",borderRadius:18,boxShadow:"0 4px 24px rgba(0,0,0,0.13)",...extra});
const gS = {background:"rgba(255,255,255,0.92)",border:"1.5px solid rgba(30,58,95,0.25)",color:"#1e3a5f",borderRadius:10,padding:"9px 16px",cursor:"pointer",...F,fontSize:"0.88rem",fontWeight:700,boxShadow:"0 2px 8px rgba(0,0,0,0.1)"};

// ─── بنك أسئلة الدرس السادس: كان وأخواتها ───
// قاعدة ذهبية: a = 0 والإجابة الصحيحة دائماً في الموضع الأول
const QB = [
  // ── مجموعة 1: التعريف والعمل ──
  {id:1,g:1,
   q:"ما عمل كان وأخواتها في الجملة الاسمية؟",
   o:["تدخل على المبتدأ فترفعه اسماً لها، وتدخل على الخبر فتنصبه خبراً لها",
      "تدخل على المبتدأ فتنصبه، وعلى الخبر فترفعه",
      "تجزم الفعل المضارع وتحذف حرف العلة",
      "تنصب المفعولَين وترفع الفاعل"],
   a:0,t:"عمل كان وأخواتها",
   f:"كان وأخواتها أفعال ناقصة تدخل على الجملة الاسمية، فترفع المبتدأ ويُسمَّى اسمها، وتنصب الخبر ويُسمَّى خبرها. مثال: كانَ التلميذُ مجتهداً."},

  {id:2,g:1,
   q:"ما المقصود بـ'الفعل الناقص' في باب كان وأخواتها؟",
   o:["هو الذي لا يكتفي بمرفوعه بل يحتاج إلى منصوب ليتم معناه",
      "هو الفعل الذي يأتي في آخر الجملة فقط",
      "هو الفعل المبني للمجهول",
      "هو الفعل الذي لا يُستعمل إلا في الماضي"],
   a:0,t:"معنى الفعل الناقص",
   f:"الفعل الناقص لا يكتفي بفاعله، بل يحتاج إلى خبر منصوب ليتم المعنى. لذا سُمِّي ناقصاً: كانَ الجوُّ حارّاً — 'حارّاً' هو الخبر المنصوب الذي أتمَّ المعنى."},

  {id:3,g:1,
   q:"كم عدد أفعال كان وأخواتها؟",
   o:["ثلاثة عشر فعلاً","سبعة أفعال","عشرة أفعال","خمسة أفعال"],
   a:0,t:"عدد أفعال كان وأخواتها",
   f:"كان وأخواتها ثلاثة عشر فعلاً: كانَ، أمسى، أصبحَ، أضحى، ظلَّ، باتَ، صارَ، ما زالَ، ما انفكَّ، ما فَتِئَ، ما بَرِحَ، ليسَ، ما دامَ."},

  {id:4,g:1,
   q:"في جملة 'كانَ التلميذُ مجتهداً' — ما إعراب 'التلميذُ'؟",
   o:["اسم كان مرفوع بالضمة الظاهرة",
      "مبتدأ مرفوع بالضمة الظاهرة",
      "فاعل مرفوع بالضمة الظاهرة",
      "خبر كان منصوب بالفتحة الظاهرة"],
   a:0,t:"إعراب اسم كان",
   f:"بعد دخول كان: التلميذُ = اسم كان مرفوع وعلامة رفعه الضمة الظاهرة. مجتهداً = خبر كان منصوب وعلامة نصبه الفتحة الظاهرة."},

  {id:5,g:1,
   q:"في جملة 'أصبحت السماءُ صافيةً' — ما إعراب 'صافيةً'؟",
   o:["خبر أصبح منصوب وعلامة نصبه الفتحة الظاهرة",
      "اسم أصبح مرفوع بالضمة",
      "حال منصوب",
      "مفعول به منصوب"],
   a:0,t:"إعراب خبر أصبح",
   f:"صافيةً: خبر أصبح منصوب وعلامة نصبه الفتحة الظاهرة. والسماءُ: اسم أصبح مرفوع بالضمة الظاهرة."},

  {id:6,g:1,
   q:"أيُّ الجمل الآتية تحتوي على فعل من أفعال كان وأخواتها؟",
   o:["أمسى المعلمُ متعَباً",
      "جاءَ الطالبُ مبكِّراً",
      "أكلَ الولدُ التفاحةَ",
      "كتبَ المديرُ الرسالةَ"],
   a:0,t:"التعرف على أفعال كان وأخواتها",
   f:"'أمسى' فعل من أفعال كان وأخواتها، يرفع المبتدأ اسماً له وينصب الخبر. أما (جاء، أكل، كتب) فأفعال تامة لها فاعل ومفعول به."},

  // ── مجموعة 2: الأقسام من حيث العمل ──
  {id:7,g:2,
   q:"تنقسم كان وأخواتها من حيث عملها إلى ثلاثة أقسام. القسم الذي يعمل بدون شرط هو:",
   o:["القسم الأول: ثمانية أفعال هي كانَ، أمسى، أصبحَ، أضحى، ظلَّ، باتَ، صارَ، ليسَ",
      "القسم الثاني: ما زالَ، ما انفكَّ، ما فَتِئَ، ما بَرِحَ",
      "القسم الثالث: ما دامَ فقط",
      "جميع الأفعال تعمل بدون شرط"],
   a:0,t:"القسم الأول من حيث العمل",
   f:"القسم الأول يعمل بدون شرط، وهو ثمانية أفعال: كانَ، أمسى، أصبحَ، أضحى، ظلَّ، باتَ، صارَ، ليسَ. تقول: كانَ زيدٌ قائماً بلا قيد."},

  {id:8,g:2,
   q:"القسم الثاني من أقسام كان وأخواتها يعمل بشرط أن يتقدمه نفي أو استفهام أو نهي. ما هذه الأفعال؟",
   o:["زالَ، انفكَّ، فَتِئَ، بَرِحَ",
      "كانَ، ظلَّ، باتَ، صارَ",
      "أمسى، أصبحَ، أضحى، ليسَ",
      "دامَ وحده"],
   a:0,t:"القسم الثاني من حيث العمل",
   f:"القسم الثاني أربعة أفعال: زالَ، انفكَّ، فَتِئَ، بَرِحَ. لا تعمل إلا إذا سبقها نفي أو استفهام أو نهي. مثال: ما زالَ الإسلامُ عظيماً."},

  {id:9,g:2,
   q:"القسم الثالث من أقسام كان وأخواتها يعمل بشرط أن يتقدمه 'ما المصدرية الظرفية'. ما هذا الفعل؟",
   o:["دامَ وحده",
      "زالَ وانفكَّ",
      "كانَ وليسَ",
      "أصبحَ وأضحى"],
   a:0,t:"القسم الثالث من حيث العمل",
   f:"القسم الثالث فعل واحد: دامَ. لا يعمل إلا إذا سبقته 'ما المصدرية الظرفية'. مثاله من القرآن: ﴿ما دمتُ حيّاً﴾ أي مدة دوامي حياً."},

  {id:10,g:2,
   q:"في جملة 'ما زالَ الإسلامُ عظيماً' — لماذا عملت 'زالَ' هنا؟",
   o:["لأن 'ما' النافية تقدمت عليها، وهو شرط عملها",
      "لأنها من القسم الأول الذي يعمل بدون شرط",
      "لأن 'ما' هنا مصدرية ظرفية",
      "لأن الجملة استفهامية"],
   a:0,t:"شرط عمل زال وأخواتها",
   f:"'زالَ' من القسم الثاني الذي يشترط تقدُّم نفي أو استفهام أو نهي. هنا تقدمت 'ما' النافية فعملت. الإسلامُ: اسمها، عظيماً: خبرها."},

  {id:11,g:2,
   q:"أيُّ الجمل الآتية فيها شرط عمل 'ما دامَ' مستوفى؟",
   o:["سأطلب العلم ما دامَ العقلُ نافعاً",
      "دامَ الفرحُ طويلاً",
      "هل دامَ الخيرُ موجوداً",
      "لا تدَمْ على الخطأ"],
   a:0,t:"شرط عمل ما دام",
   f:"'ما دامَ' في الجملة الأولى مسبوقة بـ'ما المصدرية الظرفية' وهو الشرط الوحيد لعملها. أما 'دامَ' وحدها أو مع الاستفهام أو النهي فلا تعمل عمل كان."},

  {id:12,g:2,
   q:"في جملة 'لا يَزَلْ طالبُ العلم مجتهداً' — ما الذي أجاز عمل 'يزل'؟",
   o:["تقدُّم 'لا' الناهية وهي من شروط عمل هذه الأفعال",
      "كون الفعل مضارعاً",
      "وجود 'ما' المصدرية قبلها",
      "كون الاسم معرفة"],
   a:0,t:"النهي وعمل زال وأخواتها",
   f:"'لا يَزَلْ' مجزوم بلا الناهية، وتقدُّم النهي شرطٌ من شروط عمل هذا القسم. طالبُ العلم: اسمها، مجتهداً: خبرها."},

  // ── مجموعة 3: الأقسام من حيث التصرف ──
  {id:13,g:3,
   q:"تنقسم كان وأخواتها من حيث التصرف إلى ثلاثة أقسام. ما الأفعال المتصرفة تصرفاً كاملاً؟",
   o:["كانَ، أمسى، أصبحَ، أضحى، ظلَّ، باتَ، صارَ — سبعة أفعال",
      "زالَ، انفكَّ، فَتِئَ، بَرِحَ — أربعة أفعال",
      "ليسَ، دامَ — فعلان",
      "جميع الأفعال الثلاثة عشر"],
   a:0,t:"المتصرفة تصرفاً كاملاً",
   f:"المتصرفة تصرفاً كاملاً سبعة: كانَ (يكون/كُنْ)، أمسى (يُمسي/أمسِ)، أصبحَ (يُصبح/أصْبِح)، أضحى (يُضحي/أضحِ)، ظلَّ (يَظلُّ/ظَلَّ)، باتَ (يبيت/بِتْ)، صارَ (يصير/صِرْ)."},

  {id:14,g:3,
   q:"ما الأفعال المتصرفة تصرفاً ناقصاً من أفعال كان وأخواتها؟",
   o:["زالَ، انفكَّ، فَتِئَ، بَرِحَ — تأتي ماضياً ومضارعاً فقط",
      "كانَ، ظلَّ، باتَ، صارَ",
      "ليسَ، دامَ",
      "أمسى، أصبحَ، أضحى"],
   a:0,t:"المتصرفة تصرفاً ناقصاً",
   f:"المتصرفة تصرفاً ناقصاً أربعة: زالَ (يزال)، انفكَّ (ينفكّ)، فَتِئَ (يَفتأ)، بَرِحَ (يبرح). تأتي ماضياً ومضارعاً فقط ولا تأتي أمراً."},

  {id:15,g:3,
   q:"أيُّ أفعال كان وأخواتها جامدٌ لا يتصرف مطلقاً؟",
   o:["ليسَ ودامَ — كلاهما فعل جامد يلزم صيغة الماضي",
      "كانَ وصارَ",
      "ظلَّ وبات",
      "زالَ وانفكَّ"],
   a:0,t:"الجامد من أفعال كان وأخواتها",
   f:"ليسَ ودامَ فعلان جامدان يلزمان صيغة الماضي ولا يأتي منهما مضارع ولا أمر. ويسمى كل منهما 'فعلاً جامداً'."},

  {id:16,g:3,
   q:"في جملة 'يكونُ الولدُ مجتهداً' — ما إعراب 'يكون'؟",
   o:["فعل مضارع ناقص متصرف من كان، يرفع الاسم وينصب الخبر",
      "فعل مضارع تام يرفع الفاعل فقط",
      "فعل ماضٍ ناقص",
      "فعل أمر ناقص"],
   a:0,t:"إعراب يكون",
   f:"يكون: فعل مضارع ناقص متصرف من 'كان'، يرفع الاسم وينصب الخبر. الولدُ: اسمه مرفوع. مجتهداً: خبره منصوب."},

  {id:17,g:3,
   q:"في جملة 'كُنْ صادقاً' — ما إعراب 'كُنْ'؟",
   o:["فعل أمر ناقص متصرف من كان، يرفع الاسم وينصب الخبر، واسمه ضمير مستتر تقديره أنت",
      "فعل ماضٍ ناقص",
      "فعل أمر تام له فاعل فقط",
      "اسم فعل أمر"],
   a:0,t:"إعراب فعل الأمر كُن",
   f:"كُنْ: فعل أمر ناقص مبني على السكون، يرفع الاسم وينصب الخبر. اسمه: ضمير مستتر وجوباً تقديره أنت. صادقاً: خبره منصوب."},

  {id:18,g:3,
   q:"هل تعمل صيغة المضارع والأمر من كان وأخواتها عمل صيغة الماضي؟",
   o:["نعم، فترفع الاسم وتنصب الخبر في جميع صيغها",
      "لا، يعمل المضارع فقط ولا يعمل الأمر",
      "لا، الماضي وحده يعمل",
      "نعم، لكن الأمر لا يرفع الاسم"],
   a:0,t:"عمل صيغ كان وأخواتها",
   f:"نعم، صيغة المضارع والأمر تعملان عمل الماضي: فترفعان الاسم وتنصبان الخبر. مثال: يكون الولد مجتهداً / كُن مجتهداً."},

  // ── مجموعة 4: الإعراب التطبيقي ──
  {id:19,g:4,
   q:"في جملة 'كانَ المطرُ غزيراً' — ما الإعراب الكامل للجملة؟",
   o:["كانَ: فعل ماضٍ ناقص. المطرُ: اسم كان مرفوع بالضمة. غزيراً: خبر كان منصوب بالفتحة",
      "كانَ: فعل تام. المطرُ: فاعل. غزيراً: حال",
      "كانَ: حرف نفي. المطرُ: مبتدأ. غزيراً: خبر",
      "كانَ: فعل ماضٍ تام. المطرُ: مفعول به. غزيراً: خبر"],
   a:0,t:"الإعراب الكامل لجملة كان",
   f:"كانَ: فعل ماضٍ ناقص مبني على الفتح. المطرُ: اسم كان مرفوع وعلامة رفعه الضمة الظاهرة. غزيراً: خبر كان منصوب وعلامة نصبه الفتحة الظاهرة."},

  {id:20,g:4,
   q:"في جملة 'ليسَ الطالبُ مهملاً' — ما إعراب 'ليسَ'؟",
   o:["فعل ماضٍ ناقص جامد مبني على الفتح، يرفع الاسم وينصب الخبر",
      "حرف نفي مبني لا محل له من الإعراب",
      "فعل ماضٍ تام له فاعل فقط",
      "اسم فعل ماضٍ"],
   a:0,t:"إعراب ليس",
   f:"ليسَ: فعل ماضٍ ناقص جامد مبني على الفتح. الطالبُ: اسم ليس مرفوع بالضمة. مهملاً: خبر ليس منصوب بالفتحة."},

  {id:21,g:4,
   q:"في جملة 'ما زالَ الإسلامُ منتشراً' — ما إعراب 'ما'؟",
   o:["حرف نفي مبني على السكون لا محل له من الإعراب",
      "اسم موصول في محل رفع مبتدأ",
      "ما المصدرية الظرفية",
      "اسم استفهام في محل رفع مبتدأ"],
   a:0,t:"إعراب ما في ما زال",
   f:"ما: حرف نفي مبني على السكون لا محل له من الإعراب. زالَ: فعل ماضٍ ناقص. الإسلامُ: اسمه مرفوع. منتشراً: خبره منصوب."},

  {id:22,g:4,
   q:"في جملة 'ما دامَ الأستاذُ شارحاً' — ما إعراب 'ما'؟",
   o:["ما مصدرية ظرفية، وهي شرط عمل دامَ",
      "ما نافية مثل ما في 'ما زالَ'",
      "ما استفهامية",
      "ما موصولة"],
   a:0,t:"إعراب ما في ما دام",
   f:"ما: مصدرية ظرفية، وهي شرط عمل 'دامَ'. دامَ: فعل ماضٍ ناقص. الأستاذُ: اسمه مرفوع. شارحاً: خبره منصوب."},

  {id:23,g:4,
   q:"في جملة 'أصبحَ المريضُ نشيطاً' — ما علامة نصب خبر أصبح؟",
   o:["الفتحة الظاهرة على آخره",
      "الياء نيابةً عن الفتحة",
      "الكسرة نيابةً عن الفتحة",
      "الألف نيابةً عن الفتحة"],
   a:0,t:"علامة نصب خبر أصبح",
   f:"نشيطاً: خبر أصبح منصوب وعلامة نصبه الفتحة الظاهرة على آخره، لأنه اسم مفرد منصرف. المريضُ: اسم أصبح مرفوع بالضمة الظاهرة."},

  {id:24,g:4,
   q:"في جملة 'كانَ المسلمون فاتحين' — ما علامة رفع اسم كان؟",
   o:["الواو نيابةً عن الضمة لأنه جمع مذكر سالم",
      "الضمة الظاهرة لأنه اسم مفرد",
      "الألف نيابةً عن الضمة لأنه مثنى",
      "النون لأنها علامة الرفع في الجمع"],
   a:0,t:"علامة رفع اسم كان الجمع",
   f:"المسلمون: اسم كان مرفوع وعلامة رفعه الواو نيابةً عن الضمة لأنه جمع مذكر سالم. فاتحين: خبر كان منصوب بالياء نيابةً عن الفتحة."},

  // ── مجموعة 5: التطبيق والتمييز ──
  {id:25,g:5,
   q:"أيُّ الجمل الآتية فيها 'كان' فعل تام لا ناقص؟",
   o:["وإن كانَ ذو عُسرةٍ — أي وإن وُجِدَ أو حصلَ",
      "كانَ الطالبُ مجتهداً",
      "كانَ الجوُّ صحواً",
      "كانَ المعلمُ متعَباً"],
   a:0,t:"كان التامة",
   f:"'كانَ' في الجملة الأولى بمعنى 'وُجِدَ/حصلَ' وهي تامة تكتفي بفاعلها 'ذو عسرة'. في بقية الجمل كان ناقصة تحتاج خبراً منصوباً."},

  {id:26,g:5,
   q:"في جملة 'صارَ الطالبُ ذكياً' — ما الفرق الدلالي بين 'صار' و'كان'؟",
   o:["صار تدل على التحول من حال إلى حال، بينما كان تدل على الاستمرار أو الحدوث",
      "لا فرق بينهما في المعنى",
      "صار تنصب مفعولَين بخلاف كان",
      "كان تدل على التحول وصار تدل على الثبوت"],
   a:0,t:"الفرق بين صار وكان",
   f:"صار تفيد التحول من حال إلى حال: صارَ الطالبُ ذكياً = تحوَّلَ. كان تفيد وجود الخبر في وقت مضى: كانَ الطالبُ ذكياً = كان كذلك في زمن معين."},

  {id:27,g:5,
   q:"في جملة 'بات المسافرُ متعَباً' — ما دلالة 'بات'؟",
   o:["تدل على وقوع الخبر ليلاً أو حين النوم",
      "تدل على وقوع الخبر صباحاً",
      "تدل على التحول من حال إلى حال",
      "تدل على النفي والإنكار"],
   a:0,t:"دلالة بات",
   f:"باتَ تدل على وقوع مضمون الجملة في وقت الليل. المسافرُ: اسمها مرفوع. متعَباً: خبرها منصوب. أي: كان متعباً في وقت ليله."},

  {id:28,g:5,
   q:"أيُّ الجمل الآتية فيها 'ليس' مستعملة بشكل صحيح؟",
   o:["ليسَ الكذبُ مقبولاً — ليس + اسم مرفوع + خبر منصوب",
      "ليسَ الكذبُ مقبولٌ — خبرها مرفوع",
      "لم يَلِسْ الكذبُ مقبولاً — ليس لا تتصرف",
      "كن ليساً — ليس لا يأتي منه أمر"],
   a:0,t:"استعمال ليس",
   f:"الجملة الأولى صحيحة: ليسَ (جامد) + الكذبُ (اسمها مرفوع) + مقبولاً (خبرها منصوب). ليس جامد لا يأتي منه مضارع ولا أمر، وخبره منصوب لا مرفوع."},

  {id:29,g:5,
   q:"في جملة 'ظلَّ الكاتبُ مستمعاً' — ما دلالة 'ظلَّ'؟",
   o:["تدل على وقوع الخبر نهاراً أو الاستمرار والدوام",
      "تدل على وقوع الخبر ليلاً",
      "تدل على التحول والتغير",
      "تدل على النفي"],
   a:0,t:"دلالة ظلَّ",
   f:"ظلَّ تدل على وقوع مضمون الجملة نهاراً أو على الاستمرار والدوام. الكاتبُ: اسمها مرفوع بالضمة. مستمعاً: خبرها منصوب بالفتحة."},

  {id:30,g:5,
   q:"ما الإعراب الصحيح لجملة 'أمسى الرجلُ مؤمناً'؟",
   o:["أمسى: فعل ماضٍ ناقص. الرجلُ: اسم أمسى مرفوع. مؤمناً: خبر أمسى منصوب",
      "أمسى: فعل ماضٍ تام. الرجلُ: فاعل. مؤمناً: حال",
      "أمسى: فعل ماضٍ تام. الرجلُ: مبتدأ. مؤمناً: خبر",
      "أمسى: حرف نفي. الرجلُ: مبتدأ. مؤمناً: خبر منصوب"],
   a:0,t:"الإعراب الكامل لجملة أمسى",
   f:"أمسى: فعل ماضٍ ناقص مبني على الفتح المقدر. الرجلُ: اسم أمسى مرفوع وعلامة رفعه الضمة الظاهرة. مؤمناً: خبر أمسى منصوب وعلامة نصبه الفتحة الظاهرة."},
];

// ─── العلماء ───
const SCHOLARS = [
  {id:1,name:"سيبويه",full:"عمرو بن عثمان بن قنبر",title:"إمام النحاة",era:"ت. 180هـ",icon:"📜",pts:350,about:"إمام النحاة وأعلم العرب بالنحو. ألّف 'الكتاب' أوّل مؤلَّف نحوي شامل. تتلمذ على الخليل بن أحمد الفراهيدي."},
  {id:2,name:"الخليل بن أحمد",full:"الخليل بن أحمد الفراهيدي",title:"واضع علم العروض",era:"ت. 175هـ",icon:"🎵",pts:350,about:"واضع علم العروض ومعجم العين. أستاذ سيبويه وأذكى علماء عصره."},
  {id:3,name:"ابن هشام",full:"جمال الدين بن يوسف الأنصاري",title:"أنحى من سيبويه",era:"ت. 761هـ",icon:"📖",pts:350,about:"ألّف 'مغني اللبيب' و'قطر الندى'. وصفه ابن خلدون بأنه أنحى من سيبويه."},
  {id:4,name:"ابن مالك",full:"جمال الدين محمد الطائي الجياني",title:"ناظم الألفية",era:"ت. 672هـ",icon:"📝",pts:350,about:"نظم ألفية ابن مالك التي حفظها الملايين عبر القرون."},
  {id:5,name:"ابن جني",full:"أبو الفتح عثمان بن جني",title:"شيخ العربية",era:"ت. 392هـ",icon:"✨",pts:350,about:"ألّف 'الخصائص' و'سرّ صناعة الإعراب'. كان المتنبي يُجلّه."},
  {id:6,name:"أبو علي الفارسي",full:"الحسن بن أحمد الفارسي",title:"إمام النحاة في زمانه",era:"ت. 377هـ",icon:"🌟",pts:350,about:"إمام النحاة في القرن الرابع. ألّف 'الإيضاح' و'التكملة'. أستاذ ابن جني."},
  {id:7,name:"أبو حيان",full:"أثير الدين محمد الأندلسي",title:"بحر العلوم",era:"ت. 745هـ",icon:"🌊",pts:350,about:"ألّف تفسير 'البحر المحيط' الغني بالدراسة النحوية."},
  {id:8,name:"الأشموني",full:"علي بن محمد الأشموني المصري",title:"شارح الألفية",era:"ت. 900هـ",icon:"🖋️",pts:350,about:"شرح ألفية ابن مالك شرحاً وافياً يُدرَّس في المعاهد الإسلامية."},
  {id:9,name:"قطرب",full:"أبو علي محمد بن المستنير",title:"النحوي المجتهد",era:"ت. 206هـ",icon:"🔍",pts:350,about:"تتلمذ على سيبويه. ألّف 'كتاب الأضداد' و'معاني القرآن'."},
  {id:10,name:"الفراء",full:"يحيى بن زياد الفراء",title:"إمام الكوفيين",era:"ت. 207هـ",icon:"🏛️",pts:350,about:"رأس مدرسة الكوفة النحوية. قال المأمون: لو لا الفراء لسقطت العربية."},
  {id:11,name:"الكسائي",full:"أبو الحسن علي بن حمزة",title:"مؤسّس مدرسة الكوفة",era:"ت. 189هـ",icon:"👑",pts:350,about:"مؤسّس مدرسة الكوفة النحوية. معلّم الرشيد. أحد القرّاء السبعة."},
  {id:12,name:"ابن عصفور",full:"علي بن مؤمن الحضرمي الإشبيلي",title:"نحوي الأندلس",era:"ت. 669هـ",icon:"🌙",pts:350,about:"ألّف 'المقرّب' و'الممتع في الصرف'."},
  {id:13,name:"ابن يعيش",full:"موفق الدين يعيش الحلبي",title:"شارح المفصّل",era:"ت. 643هـ",icon:"💎",pts:350,about:"اشتُهر بشرحه الواسع على 'المفصّل' للزمخشري."},
];

const COURSES = [{id:"nahw1",name:"المساعد الذَّكَالِيّ لمقرر تراكيب نحوية1",dept:"معهد تعليم اللغة العربية",code:"ARAB 201"}];
const LESSONS = [
  {id:1, title:"الدرس الأول",   sub:"الكلام وما يتألف منه",        desc:"أقسام الكلمة وعلامات كل قسم",icon:"📚", locked:true},
  {id:2, title:"الدرس الثاني",  sub:"الإعراب والبناء",              desc:"علامات الإعراب والبناء وما يختص بكل منهما",icon:"🔤", locked:true},
  {id:3, title:"الدرس الثالث",  sub:"المعرب والمبني",               desc:"المعرب والمبني من الأسماء والأفعال",icon:"📖", locked:true},
  {id:4, title:"الدرس الرابع",  sub:"المعرفة والنكرة",              desc:"أقسام الاسم من حيث التعريف والتنكير وأحكامهما",icon:"🔍", locked:true},
  {id:5, title:"الدرس الخامس",  sub:"الجملة الاسمية البسيطة",       desc:"المبتدأ والخبر — أنواعهما وأحكامهما وإعرابهما",icon:"⭐", locked:true},
  {id:6, title:"الدرس السادس",  sub:"النواسخ الفعلية: كان وأخواتها",desc:"أحكام كان وأخواتها وعملها في الجملة الاسمية",icon:"📕", locked:false, active:true},
  {id:7, title:"الدرس السابع",  sub:"النواسخ الحرفية: إنّ وأخواتها",desc:"أحكام إنّ وأخواتها وعملها في الجملة الاسمية",icon:"📗", locked:true},
  {id:8, title:"الدرس الثامن",  sub:"النواسخ الفعلية: ظنّ وأخواتها",desc:"أحكام ظنّ وأخواتها وعملها في الجملة الاسمية",icon:"📘", locked:true},
  {id:9, title:"الدرس التاسع",  sub:"الجملة الفعلية البسيطة",       desc:"مكوّنات الجملة الفعلية وأحكامها",icon:"📙", locked:true},
  {id:10,title:"الدرس العاشر",  sub:"الفعل التام",                  desc:"أنواع الفعل التام وأحكامه",icon:"📒", locked:true},
  {id:11,title:"الدرس الحادي عشر",sub:"الفاعل وأحكامه",            desc:"تعريف الفاعل وأنواعه وأحكامه الإعرابية",icon:"📓", locked:true},
  {id:12,title:"الدرس الثاني عشر",sub:"نائب الفاعل وأحكامه",       desc:"تعريف نائب الفاعل وأنواعه وأحكامه الإعرابية",icon:"📔", locked:true},
];

const STATIONS = [{id:1,x:48,y:84},{id:2,x:28,y:70},{id:3,x:40,y:56},{id:4,x:22,y:42},{id:5,x:36,y:28},{id:6,x:20,y:15},{id:7,x:32,y:4}];

// ─── مساعدات ───
function mul32(s){return function(){s|=0;s=s+0x6D2B79F5|0;var t=Math.imul(s^s>>>15,1|s);t=t+Math.imul(t^t>>>7,61|t)^t;return((t^t>>>14)>>>0)/4294967296};}
function ns(n){let h=5381;for(let i=0;i<n.length;i++)h=((h<<5)+h)+n.charCodeAt(i);return Math.abs(h)%9999999;}

function shuffleOptions(question, rng) {
  const opts = [...question.o];
  const correctText = opts[question.a];
  for(let i = opts.length - 1; i > 0; i--){
    const j = Math.floor(rng() * (i + 1));
    [opts[i], opts[j]] = [opts[j], opts[i]];
  }
  const newA = opts.findIndex(o => o === correctText);
  return { ...question, o: opts, a: newA };
}

function pickQ(seed){
  const rng=mul32(seed);
  const byG={};
  [1,2,3,4,5].forEach(g=>{byG[g]=QB.filter(q=>q.g===g)});
  const picked=[];const used=new Set();
  [1,2,3,4,5].forEach(g=>{
    const pool=byG[g];
    const idx=Math.floor(rng()*pool.length);
    picked.push(pool[idx]);used.add(pool[idx].id);
  });
  const rem=QB.filter(q=>!used.has(q.id)).sort(()=>rng()-0.5);
  picked.push(rem[0],rem[1]);
  return picked
    .sort(()=>rng()-0.5)
    .map(q => shuffleOptions(q, rng));
}

function getGrade(s){
  if(s>=7)return{label:"ممتاز 🏆",c:"#166534",bg:"#dcfce7"};
  if(s>=5)return{label:"جيد جداً ⭐",c:"#1d4ed8",bg:"#dbeafe"};
  if(s>=3)return{label:"جيد 👍",c:"#92400e",bg:"#fef3c7"};
  return{label:"يحتاج مراجعة 📚",c:"#991b1b",bg:"#fee2e2"};
}

const SK="nahw_v6_users",RK="nahw_v6_results";
function loadU(){try{return JSON.parse(localStorage.getItem(SK)||"[]")}catch{return[]}}
function saveU(u){try{localStorage.setItem(SK,JSON.stringify(u))}catch{}}
function loadR(){try{return JSON.parse(localStorage.getItem(RK)||"[]")}catch{return[]}}
function saveR(e){try{const a=loadR();a.push(e);localStorage.setItem(RK,JSON.stringify(a))}catch{}}
function getLeaderboard(){return loadU().map(u=>({name:u.name,id:u.id,level:u.level,pts:u.totalPts||0,scholars:u.scholars||[]})).sort((a,b)=>b.pts-a.pts).slice(0,10);}

// ─── صوت ───
function createCtx(){try{return new(window.AudioContext||window.webkitAudioContext)()}catch{return null}}
function pt(ctx,f,type,dur,vol=0.3,d=0){if(!ctx)return;const o=ctx.createOscillator(),g=ctx.createGain();o.connect(g);g.connect(ctx.destination);o.type=type;o.frequency.setValueAtTime(f,ctx.currentTime+d);g.gain.setValueAtTime(0,ctx.currentTime+d);g.gain.linearRampToValueAtTime(vol,ctx.currentTime+d+0.01);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+d+dur);o.start(ctx.currentTime+d);o.stop(ctx.currentTime+d+dur+0.05);}
const SFX={
  correct(c){pt(c,523,"sine",0.15,0.3,0);pt(c,659,"sine",0.15,0.3,0.12);pt(c,784,"sine",0.2,0.35,0.24)},
  wrong(c){pt(c,300,"square",0.1,0.25,0);pt(c,220,"square",0.1,0.25,0.12)},
  win(c){[523,659,784,1047].forEach((f,i)=>pt(c,f,"sine",0.3,0.35,i*0.13));pt(c,1047,"sine",0.6,0.4,0.55)},
  lose(c){[440,370,277,185].forEach((f,i)=>pt(c,f,"sawtooth",0.15,0.2,i*0.15))},
  move(c){pt(c,880,"sine",0.08,0.15,0)},
  discover(c){[600,800,1000,1200].forEach((f,i)=>pt(c,f,"sine",0.2,0.4,i*0.1))}
};

const Logo = () => (
  <div style={{width:90,height:90,borderRadius:"50%",background:"linear-gradient(135deg,#1e3a5f,#2563eb)",border:"3px solid rgba(255,255,255,0.4)",boxShadow:"0 4px 20px rgba(0,0,0,0.3)",display:"flex",alignItems:"center",justifyContent:"center",fontSize:"2.8rem"}}>
    🏛️
  </div>
);

export default function App(){
  const [screen,setScreen]   = useState("splash");
  const [user,setUser]       = useState(null);
  const [reg,setReg]         = useState({name:"",id:"",email:"",level:"",year:""});
  const [regErr,setRegErr]   = useState({});
  const [loginD,setLoginD]   = useState({id:"",name:""});
  const [loginErr,setLoginErr] = useState("");
  const [selLesson,setSelLesson] = useState(null);
  const [tab,setTab]         = useState(null);
  const [adminPass,setAdminPass] = useState("");
  const [adminOk,setAdminOk] = useState(false);
  const [adminErr,setAdminErr] = useState("");
  const [gs,setGs]           = useState(null);
  const [newScholar,setNewScholar] = useState(null);
  const [showLB,setShowLB]   = useState(false);
  const audioRef = useRef(null);

  const getCtx = () => {
    if(!audioRef.current) audioRef.current = createCtx();
    if(audioRef.current?.state==="suspended") audioRef.current.resume();
    return audioRef.current;
  };

  useEffect(()=>{setTimeout(()=>setScreen("welcome"),2200);},[]);

  const validate = () => {
    const e={};
    if(!reg.name.trim()||reg.name.trim().length<3) e.name="الاسم مطلوب (3 أحرف على الأقل)";
    if(!reg.id.trim()||!/^\d{5,12}$/.test(reg.id.trim())) e.id="الرقم الجامعي أرقام (5-12 خانة)";
    if(!reg.email.trim()||!reg.email.includes("@")) e.email="بريد إلكتروني غير صحيح";
    if(!reg.level) e.level="اختر المستوى";
    if(!reg.year)  e.year="اختر العام";
    return e;
  };

  const doReg = () => {
    const e=validate();
    if(Object.keys(e).length){setRegErr(e);return;}
    const users=loadU();
    if(users.find(u=>u.id===reg.id.trim())){setRegErr({id:"الرقم الجامعي مسجّل مسبقاً"});return;}
    const nu={...reg,id:reg.id.trim(),name:reg.name.trim(),createdAt:new Date().toISOString(),attempts:[],scholars:[],totalPts:0};
    users.push(nu);saveU(users);setUser(nu);setScreen("courses");
  };

  const doLogin = () => {
    const users=loadU();
    const f=users.find(u=>u.id===loginD.id.trim()&&u.name.trim()===loginD.name.trim());
    if(!f){setLoginErr("الرقم الجامعي أو الاسم غير صحيح");return;}
    setUser(f);setScreen("courses");
  };

  const startGame = () => {
    const seed=ns(user.name+Date.now().toString().slice(-5));
    const qs=pickQ(seed);
    setGs({
      questions:qs, current:0, popup:null,
      selected:null, attempts:2, result:null,
      showFeedback:false, fallen:false, summit:false,
      charPos:{x:48,y:84}, animating:false,
      log:[], startTime:Date.now(), finalData:null,
      showResult:false, earnedPts:0, newScholars:[],
      ptsAnim:{prev:user.totalPts||0,curr:user.totalPts||0}
    });
    setNewScholar(null);
    setTab("game");
  };

  const handleStation = (i) => {
    if(!gs||gs.animating||gs.fallen||gs.summit||i!==gs.current) return;
    setGs(g=>({...g, popup:i, selected:null, attempts:2, result:null, showFeedback:false}));
  };

  const handleAnswer = (idx) => {
    if(!gs||gs.showFeedback||gs.result==="correct") return;
    const q = gs.questions[gs.popup];
    const ok = (q.o[idx] === q.o[q.a]);
    if(ok){
      SFX.correct(getCtx());
      setGs(g=>({...g, selected:idx, result:"correct", showFeedback:true}));
    } else {
      SFX.wrong(getCtx());
      const na = gs.attempts - 1;
      if(na<=0){
        setGs(g=>({...g, selected:idx, attempts:0, result:"failed", showFeedback:true}));
      } else {
        setGs(g=>({...g, selected:idx, attempts:na, result:"wrong"}));
        setTimeout(()=>setGs(g=>({...g, selected:null, result:null})), 900);
      }
    }
  };

  const handleNext = () => {
    if(!gs) return;
    const isOk = gs.result==="correct";
    const log = [...gs.log, {s:gs.popup+1, ok:isOk, topic:gs.questions[gs.popup].t, q:gs.questions[gs.popup].q}];
    if(isOk){
      SFX.move(getCtx());
      const next = gs.current+1;
      const target = STATIONS[Math.min(next, STATIONS.length-1)];
      setGs(g=>({...g, log, showFeedback:false, popup:null, animating:true, charPos:{x:target.x,y:target.y}}));
      setTimeout(()=>{
        if(next>=STATIONS.length){ SFX.win(getCtx()); finishGame(true,next,log); }
        else setGs(g=>({...g, animating:false, current:next}));
      },700);
    } else {
      SFX.lose(getCtx());
      setGs(g=>({...g, log, showFeedback:false, popup:null, fallen:true}));
      setTimeout(()=>finishGame(false, gs.current, log), 900);
    }
  };

  const finishGame = (completed, stoppedAt, log) => {
    const now=new Date();
    const correct=log.filter(l=>l.ok).length;
    const elapsed=Math.round((Date.now()-gs.startTime)/1000);
    const weakTopics=[...new Set(log.filter(l=>!l.ok).map(l=>l.topic))].join(" / ")||"—";
    const users=loadU();
    const ui=users.findIndex(u=>u.id===user.id);
    const prevScholars=users[ui]?.scholars||[];
    const prevPts=users[ui]?.totalPts||0;
    let earnedPts=0, newScholarsArr=[];
    if(completed){
      const notYet=SCHOLARS.filter(s=>!prevScholars.includes(s.id));
      if(notYet.length>0){
        const pick=notYet[Math.floor(Math.random()*notYet.length)];
        newScholarsArr=[pick]; earnedPts=350;
      } else { earnedPts=350; }
    } else { earnedPts=correct*50; }
    const newPts=prevPts+earnedPts;
    const newScholarsIds=[...prevScholars,...newScholarsArr.map(s=>s.id)];
    if(ui>=0){
      if(!users[ui].attempts) users[ui].attempts=[];
      users[ui].attempts.push({date:now.toLocaleDateString("ar-SA"),score:correct,completed,pts:earnedPts});
      users[ui].scholars=newScholarsIds;
      users[ui].totalPts=newPts;
      saveU(users);setUser(users[ui]);
    }
    const entry={studentId:user.id,name:user.name,level:user.level||"—",year:user.year||"—",
      date:now.toLocaleDateString("ar-SA"),time:now.toLocaleTimeString("ar-SA",{hour:"2-digit",minute:"2-digit"}),
      score:correct,correct,stoppedAt:completed?"القمة":`المحطة ${stoppedAt+1}`,
      completed,weakTopics,duration:`${Math.floor(elapsed/60)}:${(elapsed%60).toString().padStart(2,"0")}`,
      log,earnedPts,totalPts:newPts,lesson:"كان وأخواتها",course:"التراكيب النحوية 1"};
    saveR(entry);
    if(newScholarsArr.length>0){SFX.discover(getCtx());setNewScholar(newScholarsArr[0]);}
    setGs(g=>({...g, finalData:entry, fallen:!completed, summit:completed, earnedPts, newScholars:newScholarsArr, ptsAnim:{prev:prevPts,curr:newPts}}));
    setTimeout(()=>setGs(g=>({...g, showResult:true})), completed?1800:400);
  };

  const stC = (i) => !gs?"#cbd5e1" : i<gs.current?"#4ade80" : i===gs.current?"#facc15" : "rgba(255,255,255,0.35)";

  // ════ RENDER ════
  if(screen==="splash") return (
    <div style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",minHeight:"100vh"}}>
        <div style={{textAlign:"center",color:"white"}}>
          <div style={{display:"flex",justifyContent:"center",marginBottom:20}}><Logo/></div>
          <h1 style={{fontSize:"1.7rem",fontWeight:700,margin:"0 0 4px",textShadow:"0 2px 8px rgba(0,0,0,0.7)"}}>المساعد الذَّكَالِيّ</h1>
          <h2 style={{fontSize:"1rem",fontWeight:500,margin:"0 0 6px",textShadow:"0 2px 8px rgba(0,0,0,0.7)"}}>لمقرر تراكيب نحوية 1</h2>
          <p style={{opacity:0.9,margin:"0 0 24px",fontSize:"0.82rem",textShadow:"0 1px 4px rgba(0,0,0,0.7)"}}>معهد تعليم اللغة العربية</p>
          <div style={{display:"flex",gap:8,justifyContent:"center"}}>
            {[0,1,2].map(i=><div key={i} style={{width:9,height:9,borderRadius:"50%",background:"rgba(255,255,255,0.7)",animation:`bounce 0.6s ${i*0.2}s infinite alternate`}}/>)}
          </div>
        </div>
      </div>
      <style>{`@keyframes bounce{to{transform:translateY(-8px)}}@keyframes popIn{0%{transform:scale(0.5);opacity:0}70%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}`}</style>
    </div>
  );

  if(screen==="welcome") return (
    <div style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",minHeight:"100vh",padding:20}}>
        <div style={{maxWidth:380,width:"100%",textAlign:"center"}}>
          <div style={{display:"flex",justifyContent:"center",marginBottom:16}}><Logo/></div>
          <h1 style={{color:"white",fontSize:"1.5rem",fontWeight:700,margin:"0 0 4px",textShadow:"0 2px 8px rgba(0,0,0,0.8)"}}>المساعد الذَّكَالِيّ</h1>
          <h2 style={{color:"rgba(255,255,255,0.95)",fontSize:"0.95rem",fontWeight:500,margin:"0 0 4px",textShadow:"0 2px 6px rgba(0,0,0,0.7)"}}>لمقرر تراكيب نحوية 1</h2>
          <p style={{color:"rgba(255,255,255,0.9)",margin:"0 0 28px",fontSize:"0.8rem",textShadow:"0 1px 4px rgba(0,0,0,0.7)"}}>معهد تعليم اللغة العربية — تعلّم تفاعلي</p>
          <button onClick={()=>setScreen("register")} style={{...gS,width:"100%",background:"rgba(255,255,255,0.95)",marginBottom:10,padding:"13px",fontSize:"0.95rem",borderRadius:12,border:"none",boxShadow:"0 4px 16px rgba(0,0,0,0.2)"}}>📝 إنشاء حساب جديد</button>
          <button onClick={()=>setScreen("login")} style={{...gS,width:"100%",background:"rgba(255,255,255,0.2)",color:"white",border:"1.5px solid rgba(255,255,255,0.5)",padding:"13px",fontSize:"0.95rem",borderRadius:12}}>🔑 تسجيل الدخول</button>
          <div style={{display:"flex",gap:12,justifyContent:"center",marginTop:14}}>
            <button onClick={()=>setShowLB(true)} style={{background:"none",border:"none",color:"rgba(255,255,255,0.8)",fontSize:"0.78rem",cursor:"pointer",...F,textShadow:"0 1px 3px rgba(0,0,0,0.5)"}}>🏆 لوحة التنافس</button>
            <button onClick={()=>{setAdminOk(false);setAdminPass("");setAdminErr("");setScreen("admin");}} style={{background:"none",border:"none",color:"rgba(255,255,255,0.6)",fontSize:"0.78rem",cursor:"pointer",...F}}>لوحة الأستاذ</button>
          </div>
        </div>
      </div>
      {showLB && <LeaderboardModal onClose={()=>setShowLB(false)} currentUser={user}/>}
      <style>{`@keyframes bounce{to{transform:translateY(-8px)}}@keyframes popIn{0%{transform:scale(0.5);opacity:0}70%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}`}</style>
    </div>
  );

  if(screen==="register") return (
    <div dir="rtl" style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,overflowY:"auto",padding:20}}>
        <div style={{maxWidth:420,margin:"0 auto"}}>
          <button onClick={()=>setScreen("welcome")} style={{...gS,marginBottom:16}}>← رجوع</button>
          <div style={{...gW({padding:"28px 22px"})}}>
            <h2 style={{textAlign:"center",color:"#1e3a5f",margin:"0 0 24px",fontSize:"1.2rem"}}>📝 إنشاء حساب</h2>
            {[{k:"name",l:"الاسم الكامل",p:"أدخل اسمك رباعياً",t:"text"},{k:"id",l:"الرقم الجامعي",p:"مثال: 44301234",t:"text"},{k:"email",l:"البريد الإلكتروني",p:"example@iau.edu.sa",t:"email"}].map(f=>(
              <div key={f.k} style={{marginBottom:13}}>
                <label style={{fontSize:"0.83rem",color:"#64748b",display:"block",marginBottom:4}}>{f.l}</label>
                <input value={reg[f.k]} onChange={e=>setReg(d=>({...d,[f.k]:e.target.value}))} onFocus={()=>setRegErr(e=>({...e,[f.k]:""}))} placeholder={f.p} type={f.t} style={{width:"100%",padding:"10px 14px",borderRadius:9,border:`1.5px solid ${regErr[f.k]?"#f87171":"#e2e8f0"}`,fontSize:"0.88rem",boxSizing:"border-box",...F}}/>
                {regErr[f.k]&&<p style={{color:"#ef4444",fontSize:"0.76rem",margin:"3px 0 0"}}>{regErr[f.k]}</p>}
              </div>
            ))}
            {[{k:"level",l:"المستوى الدراسي",opts:["الأول","الثاني","الثالث","الرابع"].map(l=>({v:l,t:`المستوى ${l}`}))},{k:"year",l:"العام الجامعي",opts:[{v:"1445-1446",t:"1445-1446 هـ"},{v:"1446-1447",t:"1446-1447 هـ"},{v:"1447-1448",t:"1447-1448 هـ"}]}].map(f=>(
              <div key={f.k} style={{marginBottom:14}}>
                <label style={{fontSize:"0.83rem",color:"#64748b",display:"block",marginBottom:4}}>{f.l}</label>
                <select value={reg[f.k]} onChange={e=>setReg(d=>({...d,[f.k]:e.target.value}))} style={{width:"100%",padding:"10px 14px",borderRadius:9,border:`1.5px solid ${regErr[f.k]?"#f87171":"#e2e8f0"}`,fontSize:"0.88rem",boxSizing:"border-box",...F}}>
                  <option value="">اختر...</option>
                  {f.opts.map(o=><option key={o.v} value={o.v}>{o.t}</option>)}
                </select>
                {regErr[f.k]&&<p style={{color:"#ef4444",fontSize:"0.76rem",margin:"3px 0 0"}}>{regErr[f.k]}</p>}
              </div>
            ))}
            <button onClick={doReg} style={{width:"100%",background:"#1e3a5f",color:"white",border:"none",borderRadius:10,padding:"13px",fontSize:"0.95rem",cursor:"pointer",fontWeight:700,marginTop:4,...F}}>إنشاء الحساب ✓</button>
          </div>
        </div>
      </div>
    </div>
  );

  if(screen==="login") return (
    <div style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",minHeight:"100vh",padding:20}}>
        <div style={{maxWidth:380,width:"100%"}}>
          <button onClick={()=>setScreen("welcome")} style={{...gS,marginBottom:16}}>← رجوع</button>
          <div style={{...gW({padding:"28px 22px"})}}>
            <h2 style={{textAlign:"center",color:"#1e3a5f",margin:"0 0 22px",fontSize:"1.2rem"}}>🔑 تسجيل الدخول</h2>
            {[{k:"id",l:"الرقم الجامعي",p:"أدخل رقمك الجامعي"},{k:"name",l:"الاسم الكامل",p:"أدخل اسمك كما سجّلت"}].map(f=>(
              <div key={f.k} style={{marginBottom:14}}>
                <label style={{fontSize:"0.83rem",color:"#64748b",display:"block",marginBottom:4}}>{f.l}</label>
                <input value={loginD[f.k]} onChange={e=>setLoginD(d=>({...d,[f.k]:e.target.value}))} placeholder={f.p} style={{width:"100%",padding:"10px 14px",borderRadius:9,border:"1.5px solid #e2e8f0",fontSize:"0.88rem",boxSizing:"border-box",...F}}/>
              </div>
            ))}
            {loginErr&&<p style={{color:"#ef4444",fontSize:"0.85rem",textAlign:"center",margin:"0 0 10px"}}>{loginErr}</p>}
            <button onClick={doLogin} style={{width:"100%",background:"#1e3a5f",color:"white",border:"none",borderRadius:10,padding:"13px",fontSize:"0.95rem",cursor:"pointer",fontWeight:700,...F}}>دخول</button>
          </div>
        </div>
      </div>
    </div>
  );

  if(screen==="courses") return (
    <div dir="rtl" style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,overflowY:"auto",padding:20}}>
        <div style={{maxWidth:480,margin:"0 auto"}}>
          <div style={{display:"flex",justifyContent:"space-between",alignItems:"flex-start",marginBottom:20}}>
            <div>
              <p style={{color:"rgba(255,255,255,0.8)",margin:0,fontSize:"0.8rem",textShadow:"0 1px 3px rgba(0,0,0,0.5)"}}>مرحباً،</p>
              <h2 style={{color:"white",margin:"2px 0 0",fontSize:"1.1rem",fontWeight:700,textShadow:"0 2px 6px rgba(0,0,0,0.6)"}}>{user?.name}</h2>
            </div>
            <div style={{...gW({padding:"8px 14px",borderRadius:12,textAlign:"center"})}}>
              <p style={{color:"#64748b",margin:0,fontSize:"0.7rem"}}>نقاطي</p>
              <p style={{color:"#d97706",margin:0,fontSize:"1.2rem",fontWeight:700}}>{(user?.totalPts||0).toLocaleString()}</p>
            </div>
          </div>
          <div style={{display:"flex",gap:8,marginBottom:16}}>
            <button onClick={()=>setShowLB(true)} style={{...gS,flex:1,textAlign:"center",padding:"10px",fontSize:"0.82rem"}}>🏆 لوحة التنافس</button>
            <button onClick={()=>{setTab("scholars");setSelLesson({subtitle:"أعلام النحو",title:""});setScreen("lesson");}} style={{...gS,flex:1,textAlign:"center",padding:"10px",fontSize:"0.82rem"}}>🏛️ علمائي ({(user?.scholars||[]).length}/{SCHOLARS.length})</button>
          </div>
          <h3 style={{color:"white",margin:"0 0 14px",fontSize:"0.9rem",textShadow:"0 1px 4px rgba(0,0,0,0.5)"}}>📚 مقرراتي</h3>
          {COURSES.map(c=>(
            <div key={c.id} onClick={()=>setScreen("lessons")} style={{...gW({padding:"18px 20px",marginBottom:12,cursor:"pointer"})}}>
              <div style={{display:"flex",alignItems:"center",gap:14}}>
                <div style={{width:50,height:50,borderRadius:12,background:"linear-gradient(135deg,#1e3a5f,#2563eb)",display:"flex",alignItems:"center",justifyContent:"center",fontSize:"1.5rem",flexShrink:0,border:"1.5px solid rgba(255,255,255,0.2)"}}>🏛️</div>
                <div>
                  <p style={{fontWeight:700,color:"#1e3a5f",margin:0,fontSize:"0.88rem"}}>{c.name}</p>
                  <p style={{color:"#64748b",margin:"2px 0 0",fontSize:"0.78rem"}}>{c.dept} • {c.code}</p>
                </div>
              </div>
              {(user?.attempts||[]).length>0&&(
                <div style={{marginTop:10,background:"#f0f9ff",borderRadius:8,padding:"7px 12px",fontSize:"0.78rem",color:"#0369a1",display:"flex",gap:16}}>
                  <span>📊 المحاولات: {user.attempts.length}</span>
                  <span>⭐ آخر نتيجة: {user.attempts[user.attempts.length-1]?.score}/7</span>
                  <span>🥇 {user.totalPts||0} نقطة</span>
                </div>
              )}
            </div>
          ))}
          <button onClick={()=>setScreen("welcome")} style={{background:"none",border:"none",color:"rgba(255,255,255,0.5)",fontSize:"0.78rem",cursor:"pointer",marginTop:8,...F}}>تسجيل الخروج</button>
        </div>
      </div>
      {showLB&&<LeaderboardModal onClose={()=>setShowLB(false)} currentUser={user}/>}
      <style>{`@keyframes popIn{0%{transform:scale(0.5);opacity:0}70%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}`}</style>
    </div>
  );

  if(screen==="lessons") return (
    <div dir="rtl" style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,overflowY:"auto",padding:20}}>
        <div style={{maxWidth:480,margin:"0 auto"}}>
          <div style={{display:"flex",alignItems:"center",gap:12,marginBottom:20}}>
            <button onClick={()=>setScreen("courses")} style={gS}>← رجوع</button>
            <h2 style={{color:"white",margin:0,fontSize:"1rem",fontWeight:700,textShadow:"0 2px 6px rgba(0,0,0,0.6)"}}>التراكيب النحوية 1</h2>
          </div>
          {LESSONS.map(l=>(
            <div key={l.id} onClick={()=>{if(!l.locked){setSelLesson(l);setTab(null);setGs(null);setScreen("lesson");}}}
              style={{...gW({padding:"15px 17px",marginBottom:10,cursor:l.locked?"not-allowed":"pointer",opacity:l.locked?0.5:1,border:l.active?"2.5px solid #fbbf24":"1.5px solid rgba(30,58,95,0.15)"})}}>
              <div style={{display:"flex",alignItems:"center",gap:12}}>
                <div style={{fontSize:"1.7rem"}}>{l.locked?"🔒":l.icon}</div>
                <div style={{flex:1}}>
                  <p style={{fontWeight:700,color:"#1e3a5f",margin:0,fontSize:"0.87rem"}}>{l.title}: {l.sub}</p>
                  <p style={{color:"#64748b",margin:"3px 0 0",fontSize:"0.76rem"}}>{l.desc}</p>
                </div>
                {l.active&&<span style={{background:"#fbbf24",color:"#1e3a5f",borderRadius:20,padding:"3px 10px",fontSize:"0.7rem",fontWeight:700,flexShrink:0}}>متاح</span>}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );

  if(screen==="lesson") return (
    <div dir="rtl" style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,overflowY:"auto",padding:20}}>
        <div style={{maxWidth:480,margin:"0 auto"}}>
          <div style={{display:"flex",alignItems:"center",gap:12,marginBottom:18}}>
            <button onClick={()=>{if(tab){setTab(null);setGs(null);}else setScreen("lessons");}} style={gS}>← رجوع</button>
            <div style={{flex:1}}>
              <h2 style={{color:"white",margin:0,fontSize:"0.95rem",fontWeight:700,textShadow:"0 2px 6px rgba(0,0,0,0.6)"}}>{selLesson?.sub}</h2>
              <p style={{color:"rgba(255,255,255,0.8)",margin:0,fontSize:"0.72rem"}}>{selLesson?.title}</p>
            </div>
            <div style={{...gW({padding:"6px 12px",borderRadius:10,textAlign:"center"})}}>
              <p style={{color:"#64748b",margin:0,fontSize:"0.65rem"}}>نقاطي</p>
              <p style={{color:"#d97706",margin:0,fontSize:"0.95rem",fontWeight:700}}>{(user?.totalPts||0).toLocaleString()}</p>
            </div>
          </div>

          {tab==="scholars" && <ScholarsPage user={user} scholars={SCHOLARS}/>}
          {tab==="pdf"     && <PDFViewer/>}
          {tab==="podcast" && <PodcastViewer/>}
          {tab==="video"   && <VideoViewer/>}
          {tab==="game" && gs && !gs.showResult && (
            <GameBoard gs={gs} user={user} stC={stC} handleStation={handleStation} handleAnswer={handleAnswer} handleNext={handleNext} STATIONS={STATIONS}/>
          )}
          {tab==="game" && gs?.showResult && (
            <GameResult gs={gs} user={user} SCHOLARS={SCHOLARS} startGame={startGame} setTab={setTab} setGs={setGs}/>
          )}

          {!tab && (
            <>
              <div style={{...gW({padding:"14px 16px",marginBottom:18,background:"rgba(30,58,95,0.85)",border:"1.5px solid rgba(255,255,255,0.2)"})}}>
                <p style={{margin:"0 0 3px",fontWeight:700,fontSize:"0.88rem",color:"white"}}>📌 {selLesson?.sub}</p>
                <p style={{margin:0,fontSize:"0.82rem",lineHeight:1.7,color:"rgba(255,255,255,0.85)"}}>{selLesson?.desc}</p>
              </div>
              <div style={{...gW({padding:"12px 16px",marginBottom:14,background:"rgba(30,58,95,0.85)",border:"1.5px solid rgba(255,255,255,0.2)"})}}>
                <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}}>
                  <p style={{color:"white",fontWeight:700,fontSize:"0.85rem",margin:0}}>🏛️ أعلام النحو المكتشفة</p>
                  <span style={{background:"#fbbf24",color:"#1e3a5f",borderRadius:20,padding:"3px 10px",fontSize:"0.8rem",fontWeight:700}}>{(user?.scholars||[]).length}/{SCHOLARS.length}</span>
                </div>
                <div style={{display:"flex",gap:6,flexWrap:"wrap"}}>
                  {SCHOLARS.map(s=>{const have=(user?.scholars||[]).includes(s.id);return(
                    <div key={s.id} title={s.name} style={{width:34,height:34,borderRadius:"50%",background:have?"rgba(250,204,21,0.9)":"rgba(255,255,255,0.3)",border:`2px solid ${have?"#fbbf24":"rgba(255,255,255,0.3)"}`,display:"flex",alignItems:"center",justifyContent:"center",fontSize:"0.95rem",filter:have?"none":"grayscale(1) opacity(0.5)"}}>
                      {s.icon}
                    </div>
                  );})}
                </div>
              </div>
              <h3 style={{color:"white",margin:"0 0 12px",fontSize:"0.9rem",textShadow:"0 1px 4px rgba(0,0,0,0.5)"}}>محتويات الدرس</h3>
              {[
                {id:"pdf",    icon:"📄",title:"العرض التقديمي",    sub:"ملف PDF — كان وأخواتها"},
                {id:"podcast",icon:"🎙️",title:"البودكاست التعليمي",sub:"حوار علمي تفاعلي عن الدرس"},
                {id:"video",  icon:"▶️",title:"الفيديو التعليمي",  sub:"شرح مرئي على يوتيوب"},
                {id:"game",   icon:"⛰️",title:"لعبة جبل النحو",   sub:"اكتشف أعلام النحو واجمع النقاط"},
              ].map(item=>(
                <div key={item.id} onClick={()=>item.id==="game"?startGame():setTab(item.id)}
                  style={{...gW({padding:"15px 17px",marginBottom:10,cursor:"pointer",display:"flex",alignItems:"center",gap:14})}}>
                  <div style={{width:48,height:48,borderRadius:12,background:"#f0f9ff",border:"1.5px solid #bfdbfe",display:"flex",alignItems:"center",justifyContent:"center",fontSize:"1.5rem",flexShrink:0}}>{item.icon}</div>
                  <div style={{flex:1}}>
                    <p style={{fontWeight:700,color:"#1e293b",margin:0,fontSize:"0.9rem"}}>{item.title}</p>
                    <p style={{color:"#64748b",margin:"2px 0 0",fontSize:"0.78rem"}}>{item.sub}</p>
                  </div>
                  <span style={{color:"#94a3b8",fontSize:"1.1rem"}}>›</span>
                </div>
              ))}
            </>
          )}
        </div>
      </div>
      {newScholar && <ScholarDiscovery scholar={newScholar} onClose={()=>setNewScholar(null)}/>}
      {showLB && <LeaderboardModal onClose={()=>setShowLB(false)} currentUser={user}/>}
      <style>{`@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}@keyframes popIn{0%{transform:scale(0.5);opacity:0}70%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}@keyframes glow{0%,100%{box-shadow:0 0 10px rgba(250,204,21,0.4)}50%{box-shadow:0 0 25px rgba(250,204,21,0.8)}}`}</style>
    </div>
  );

  if(screen==="admin") return (
    <div dir="rtl" style={{...bgStyle}}>
      <div style={overlay}/>
      <div style={{...rel,overflowY:"auto",padding:20}}>
        <div style={{maxWidth:720,margin:"0 auto"}}>
          <div style={{display:"flex",alignItems:"center",gap:12,marginBottom:20}}>
            <button onClick={()=>setScreen("welcome")} style={gS}>← رجوع</button>
            <h2 style={{color:"white",margin:0,fontSize:"1.1rem",fontWeight:700,textShadow:"0 2px 6px rgba(0,0,0,0.6)"}}>🎓 لوحة إدارة الأستاذ</h2>
          </div>
          {!adminOk?(
            <div style={{...gW({padding:"28px 24px",maxWidth:340,margin:"0 auto",textAlign:"center"})}}>
              <div style={{fontSize:"2.5rem",marginBottom:12}}>🔐</div>
              <p style={{color:"#64748b",marginBottom:16,fontSize:"0.88rem"}}>أدخل كلمة مرور الأستاذ</p>
              <input type="password" value={adminPass} onChange={e=>setAdminPass(e.target.value)} onKeyDown={e=>e.key==="Enter"&&(adminPass==="teacher123"?setAdminOk(true):setAdminErr("كلمة المرور غير صحيحة"))} placeholder="كلمة المرور" style={{width:"100%",padding:"10px 14px",borderRadius:9,border:`1.5px solid ${adminErr?"#f87171":"#e2e8f0"}`,fontSize:"0.92rem",textAlign:"center",boxSizing:"border-box",...F,marginBottom:8}}/>
              {adminErr&&<p style={{color:"#ef4444",fontSize:"0.8rem",margin:"0 0 8px"}}>{adminErr}</p>}
              <button onClick={()=>adminPass==="teacher123"?setAdminOk(true):setAdminErr("كلمة المرور غير صحيحة")} style={{width:"100%",background:"#1e3a5f",color:"white",border:"none",borderRadius:9,padding:"11px",fontSize:"0.92rem",cursor:"pointer",...F,fontWeight:600}}>دخول</button>
              <p style={{color:"#94a3b8",fontSize:"0.72rem",marginTop:10}}>كلمة المرور: teacher123</p>
            </div>
          ):<AdminDashboard/>}
        </div>
      </div>
    </div>
  );
  return null;
}

// ════ مكوّنات مساعدة ════

function GameBoard({gs,user,stC,handleStation,handleAnswer,handleNext,STATIONS}){
  const popupQ = gs.popup!==null ? gs.questions[gs.popup] : null;
  return(
    <div>
      <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:10}}>
        <span style={{...gW({padding:"4px 12px",borderRadius:10,fontSize:"0.78rem",fontWeight:600,color:"#1e3a5f"})}}>المحطة {gs.current}/7</span>
        <span style={{...gW({padding:"4px 12px",borderRadius:10,fontSize:"0.82rem",fontWeight:700,color:"#d97706"})}}>⭐ {(user?.totalPts||0).toLocaleString()} نقطة</span>
      </div>
      <div style={{position:"relative",width:"100%",height:360}}>
        <svg viewBox="0 0 480 390" style={{position:"absolute",inset:0,width:"100%",height:"100%"}} xmlns="http://www.w3.org/2000/svg">
          <polygon points="240,18 55,375 425,375" fill="rgba(80,100,60,0.55)"/>
          <polygon points="240,18 95,375 385,375" fill="rgba(100,130,70,0.5)"/>
          <polygon points="240,18 210,100 270,100" fill="rgba(255,255,255,0.75)"/>
          <rect x="0" y="374" width="480" height="36" fill="rgba(74,222,128,0.35)" rx="4"/>
          <polyline points={STATIONS.map(s=>`${s.x/100*480},${s.y/100*375}`).join(" ")} fill="none" stroke="rgba(255,255,255,0.5)" strokeWidth="2" strokeDasharray="6,4"/>
        </svg>
        {STATIONS.map((s,i)=>(
          <button key={s.id} onClick={()=>handleStation(i)}
            style={{position:"absolute",left:`calc(${s.x}% - 15px)`,top:`calc(${s.y}% - 15px)`,
              width:30,height:30,borderRadius:"50%",background:stC(i),
              border:i===gs.current?"3px solid white":"2px solid rgba(255,255,255,0.5)",
              cursor:i===gs.current&&!gs.animating?"pointer":"default",
              fontSize:"0.65rem",fontWeight:700,color:i<gs.current?"#166534":"#1e293b",
              display:"flex",alignItems:"center",justifyContent:"center",
              boxShadow:i===gs.current?"0 0 0 4px rgba(250,204,21,0.5)":i<gs.current?"0 0 8px rgba(74,222,128,0.5)":"none",
              zIndex:3,padding:0,...F,
              animation:i===gs.current?"glow 1.5s infinite":"none"}}>
            {i<gs.current?"✓":s.id}
          </button>
        ))}
        {!gs.fallen&&(
          <div style={{position:"absolute",left:`calc(${gs.charPos.x}% + 16px)`,top:`calc(${gs.charPos.y}% - 22px)`,
            fontSize:"1.3rem",transition:gs.animating?"all 0.65s cubic-bezier(0.4,0,0.2,1)":"none",zIndex:4,
            filter:"drop-shadow(0 2px 6px rgba(0,0,0,0.4))"}}>🧑‍🎓</div>
        )}
        {gs.fallen&&<div style={{position:"absolute",left:"46%",top:"88%",fontSize:"1.5rem",zIndex:4,transform:"rotate(90deg)"}}>🧑‍🎓</div>}
      </div>
      <div style={{background:"rgba(255,255,255,0.3)",borderRadius:20,height:7,overflow:"hidden",margin:"8px 0 4px"}}>
        <div style={{height:"100%",width:`${(gs.current/7)*100}%`,background:"linear-gradient(90deg,#22c55e,#86efac)",borderRadius:20,transition:"width 0.5s"}}/>
      </div>
      <p style={{textAlign:"center",color:"rgba(255,255,255,0.9)",fontSize:"0.75rem",margin:0,textShadow:"0 1px 3px rgba(0,0,0,0.5)"}}>
        اضغط على المحطة الصفراء • أكمل الجبل لاكتشاف عالم نحوي وكسب 350 نقطة!
      </p>

      {popupQ && !gs.fallen && !gs.summit && (
        <div style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.65)",zIndex:50,display:"flex",alignItems:"center",justifyContent:"center",padding:16}}>
          <div dir="rtl" style={{...gW({padding:"20px 18px",maxWidth:440,width:"100%",maxHeight:"90vh",overflowY:"auto",animation:"slideUp 0.3s ease"})}}>
            <div style={{display:"flex",justifyContent:"space-between",marginBottom:10}}>
              <span style={{background:"#dbeafe",color:"#1d4ed8",borderRadius:20,padding:"3px 12px",fontSize:"0.76rem",fontWeight:600}}>المحطة {gs.popup+1}</span>
              <span style={{background:"#f0fdf4",color:"#166534",borderRadius:20,padding:"3px 12px",fontSize:"0.76rem",fontWeight:600}}>{popupQ.t}</span>
            </div>
            <p style={{fontWeight:700,fontSize:"0.95rem",color:"#1e293b",textAlign:"center",margin:"0 0 14px",lineHeight:1.8}}>{popupQ.q}</p>
            <div style={{display:"flex",flexDirection:"column",gap:8}}>
              {popupQ.o.map((opt,i)=>{
                const isSelected = gs.selected===i;
                let bg="white", border="1.5px solid #e2e8f0", color="#334155";
                if(isSelected && gs.result==="correct")       {bg="#dcfce7";border="1.5px solid #4ade80";color="#166534";}
                else if(isSelected && (gs.result==="wrong"||gs.result==="failed")) {bg="#fee2e2";border="1.5px solid #f87171";color="#991b1b";}
                return(
                  <button key={i} onClick={()=>handleAnswer(i)} disabled={gs.showFeedback}
                    style={{background:bg,border,color,borderRadius:9,padding:"10px 14px",
                      fontSize:"0.88rem",cursor:gs.showFeedback?"default":"pointer",
                      textAlign:"right",...F,fontWeight:isSelected?600:400,
                      opacity:gs.showFeedback&&!isSelected?0.5:1}}>
                    {opt}
                  </button>
                );
              })}
            </div>
            {!gs.showFeedback&&(
              <p style={{textAlign:"center",fontSize:"0.78rem",color:"#64748b",marginTop:8}}>
                {gs.attempts===2?"لديك محاولتان":"⚠️ محاولة أخيرة!"}
                {gs.result==="wrong"&&<span style={{color:"#ef4444",fontWeight:600}}> — خطأ، حاول مجدداً</span>}
              </p>
            )}
            {gs.showFeedback&&(
              <div style={{marginTop:12}}>
                <div style={{background:gs.result==="correct"?"#dcfce7":"#fee2e2",borderRadius:9,padding:"11px 13px",marginBottom:8}}>
                  <p style={{fontWeight:700,color:gs.result==="correct"?"#166534":"#dc2626",fontSize:"0.85rem",margin:"0 0 4px"}}>
                    {gs.result==="correct"?"✅ إجابة صحيحة!":"❌ انتهت المحاولات"}
                  </p>
                  <p style={{color:gs.result==="correct"?"#166534":"#991b1b",fontSize:"0.82rem",margin:0,lineHeight:1.8}}>
                    💡 {popupQ.f}
                  </p>
                </div>
                {gs.result==="failed"&&(
                  <div style={{background:"#fef3c7",borderRadius:8,padding:"7px 12px",marginBottom:8,fontSize:"0.8rem",color:"#92400e"}}>
                    <strong>الصواب:</strong> {popupQ.o[popupQ.a]}
                  </div>
                )}
                <button onClick={handleNext}
                  style={{width:"100%",background:gs.result==="correct"?"#059669":"#dc2626",color:"white",border:"none",borderRadius:9,padding:"11px",fontSize:"0.92rem",cursor:"pointer",fontWeight:700,...F}}>
                  {gs.result==="correct"?"التالي ←":"انتهت اللعبة"}
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {gs.fallen&&!gs.showResult&&(
        <div style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.75)",zIndex:50,display:"flex",alignItems:"center",justifyContent:"center"}}>
          <div style={{...gW({padding:"28px 24px",textAlign:"center"})}}>
            <div style={{fontSize:"3rem",marginBottom:8}}>😱</div>
            <h2 style={{color:"#dc2626",margin:"0 0 6px"}}>سقطتَ من الجبل!</h2>
            <p style={{color:"#64748b",fontSize:"0.87rem"}}>جارٍ احتساب النقاط...</p>
          </div>
        </div>
      )}
    </div>
  );
}

function GameResult({gs,user,SCHOLARS,startGame,setTab,setGs}){
  const fd=gs.finalData;
  const grade=getGrade(fd.score);
  const newS=gs.newScholars||[];
  const [showLB,setShowLB]=useState(false);
  return(
    <div style={{...gW({padding:"22px 18px"})}}>
      <div style={{textAlign:"center",marginBottom:14}}>
        <div style={{fontSize:"2.5rem"}}>{fd.completed?"🏆":"😓"}</div>
        <h2 style={{fontSize:"1.1rem",fontWeight:700,color:"#1e3a5f",margin:"5px 0 2px"}}>{fd.completed?"أحسنت! وصلتَ للقمة!":"حاول مجدداً!"}</h2>
        <p style={{color:"#64748b",margin:0,fontSize:"0.83rem"}}>{fd.name} | المستوى {fd.level}</p>
      </div>
      <div style={{background:grade.bg,borderRadius:11,padding:"11px",textAlign:"center",marginBottom:12}}>
        <p style={{fontSize:"2rem",fontWeight:700,color:grade.c,margin:0}}>{fd.score}/7</p>
        <p style={{fontSize:"0.92rem",fontWeight:600,color:grade.c,margin:"2px 0 0"}}>{grade.label}</p>
      </div>
      <div style={{background:"linear-gradient(135deg,#fef3c7,#fde68a)",borderRadius:11,padding:"12px 16px",marginBottom:12,textAlign:"center"}}>
        <p style={{color:"#92400e",fontSize:"0.8rem",margin:"0 0 4px",fontWeight:600}}>🏅 نقاط مكتسبة هذه الجولة</p>
        <p style={{color:"#d97706",fontSize:"1.8rem",fontWeight:700,margin:0}}>+{fd.earnedPts}</p>
        <p style={{color:"#92400e",fontSize:"0.82rem",margin:"4px 0 0"}}>إجمالي نقاطك: <strong>{gs.ptsAnim?.curr?.toLocaleString()}</strong></p>
      </div>
      {newS.length>0&&(
        <div style={{background:"#fef9c3",border:"2px solid #fbbf24",borderRadius:11,padding:"12px 14px",marginBottom:12}}>
          <p style={{fontWeight:700,color:"#92400e",fontSize:"0.85rem",margin:"0 0 8px"}}>🏛️ عالم جديد مكتشف!</p>
          {newS.map(s=>(
            <div key={s.id} style={{display:"flex",gap:10,alignItems:"center"}}>
              <span style={{fontSize:"1.5rem"}}>{s.icon}</span>
              <div>
                <p style={{fontWeight:700,color:"#92400e",margin:0,fontSize:"0.88rem"}}>{s.name}</p>
                <p style={{color:"#b45309",margin:0,fontSize:"0.75rem"}}>{s.title} | {s.era}</p>
                <p style={{color:"#059669",margin:"2px 0 0",fontSize:"0.75rem",fontWeight:600}}>+{s.pts} نقطة ✓</p>
              </div>
            </div>
          ))}
        </div>
      )}
      <div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:8,marginBottom:12}}>
        {[{l:"الوقت",v:fd.duration},{l:"الحالة",v:fd.completed?"أتمّ ✅":"سقط ❌"},{l:"المحطة",v:fd.stoppedAt},{l:"صحيح",v:`${fd.correct}/7`}].map((s,i)=>(
          <div key={i} style={{background:"#f8fafc",borderRadius:8,padding:"8px",textAlign:"center"}}>
            <p style={{color:"#94a3b8",fontSize:"0.7rem",margin:"0 0 2px"}}>{s.l}</p>
            <p style={{color:"#1e293b",fontSize:"0.85rem",fontWeight:600,margin:0}}>{s.v}</p>
          </div>
        ))}
      </div>
      {fd.weakTopics!=="—"&&<div style={{background:"#fff7ed",border:"1px solid #fed7aa",borderRadius:9,padding:"9px 12px",marginBottom:12}}><p style={{color:"#92400e",fontWeight:600,fontSize:"0.8rem",margin:"0 0 2px"}}>📌 للمراجعة:</p><p style={{color:"#b45309",fontSize:"0.8rem",margin:0}}>{fd.weakTopics}</p></div>}
      <div style={{display:"flex",gap:8,marginBottom:8}}>
        <button onClick={()=>setShowLB(true)} style={{flex:1,background:"#f0f9ff",color:"#1d4ed8",border:"1.5px solid #bfdbfe",borderRadius:9,padding:"10px",fontSize:"0.85rem",cursor:"pointer",fontWeight:600,...F}}>🏆 لوحة التنافس</button>
        <button onClick={startGame} style={{flex:1,background:"#1e3a5f",color:"white",border:"none",borderRadius:9,padding:"10px",fontSize:"0.85rem",cursor:"pointer",fontWeight:700,...F}}>🔄 جولة جديدة</button>
      </div>
      <button onClick={()=>{setTab(null);setGs(null);}} style={{width:"100%",background:"#f8fafc",color:"#64748b",border:"1px solid #e2e8f0",borderRadius:9,padding:"10px",fontSize:"0.85rem",cursor:"pointer",...F}}>← العودة للدرس</button>
      {showLB&&<LeaderboardModal onClose={()=>setShowLB(false)} currentUser={user}/>}
    </div>
  );
}

function LeaderboardModal({onClose,currentUser}){
  const lb=getLeaderboard();
  const medals=["🥇","🥈","🥉"];
  return(
    <div style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.7)",zIndex:100,display:"flex",alignItems:"center",justifyContent:"center",padding:16}}>
      <div dir="rtl" style={{...gW({padding:"24px 20px",maxWidth:440,width:"100%",maxHeight:"85vh",overflowY:"auto",animation:"popIn 0.3s ease"})}}>
        <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:18}}>
          <h3 style={{margin:0,color:"#1e3a5f",fontSize:"1.1rem"}}>🏆 لوحة التنافس</h3>
          <button onClick={onClose} style={{background:"none",border:"none",fontSize:"1.3rem",cursor:"pointer",color:"#64748b"}}>✕</button>
        </div>
        {lb.length===0?<p style={{textAlign:"center",color:"#94a3b8"}}>لا يوجد طلاب بعد</p>:
          lb.map((s,i)=>{
            const isMe=currentUser&&s.id===currentUser.id;
            return(
              <div key={i} style={{display:"flex",alignItems:"center",gap:12,padding:"10px 12px",marginBottom:8,background:isMe?"#eff6ff":"#f8fafc",borderRadius:10,border:isMe?"2px solid #2563eb":"1px solid #f1f5f9"}}>
                <span style={{fontSize:"1.1rem",width:26,textAlign:"center"}}>{i<3?medals[i]:`${i+1}`}</span>
                <div style={{flex:1}}>
                  <p style={{fontWeight:700,color:isMe?"#1d4ed8":"#1e293b",margin:0,fontSize:"0.85rem"}}>{s.name}{isMe?" (أنت)":""}</p>
                  <p style={{color:"#94a3b8",margin:0,fontSize:"0.72rem"}}>المستوى {s.level} | 🏛️ {s.scholars.length}/{SCHOLARS.length} عالم</p>
                </div>
                <div style={{textAlign:"left"}}>
                  <p style={{fontWeight:700,color:"#d97706",margin:0,fontSize:"1rem"}}>{s.pts.toLocaleString()}</p>
                  <p style={{color:"#94a3b8",margin:0,fontSize:"0.7rem"}}>نقطة</p>
                </div>
              </div>
            );
          })
        }
      </div>
    </div>
  );
}

function ScholarDiscovery({scholar,onClose}){
  return(
    <div style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.8)",zIndex:200,display:"flex",alignItems:"center",justifyContent:"center",padding:20}}>
      <div dir="rtl" style={{...gW({padding:"28px 24px",maxWidth:360,width:"100%",textAlign:"center",animation:"popIn 0.4s ease",border:"3px solid #fbbf24"})}}>
        <div style={{fontSize:"3rem",marginBottom:6}}>{scholar.icon}</div>
        <div style={{background:"linear-gradient(135deg,#fbbf24,#f59e0b)",borderRadius:20,padding:"4px 16px",display:"inline-block",margin:"8px 0"}}>
          <p style={{color:"white",fontWeight:700,fontSize:"0.85rem",margin:0}}>🎉 اكتشفت عالماً جديداً!</p>
        </div>
        <h2 style={{color:"#92400e",fontSize:"1.3rem",margin:"8px 0 4px"}}>{scholar.name}</h2>
        <p style={{color:"#b45309",fontSize:"0.8rem",margin:"0 0 4px"}}>{scholar.full}</p>
        <div style={{display:"flex",gap:6,justifyContent:"center",marginBottom:12}}>
          <span style={{background:"#fde68a",color:"#92400e",borderRadius:20,padding:"3px 10px",fontSize:"0.75rem",fontWeight:600}}>{scholar.title}</span>
          <span style={{background:"#fed7aa",color:"#9a3412",borderRadius:20,padding:"3px 10px",fontSize:"0.75rem"}}>{scholar.era}</span>
        </div>
        <p style={{color:"#64748b",fontSize:"0.83rem",lineHeight:1.8,margin:"0 0 14px"}}>{scholar.about}</p>
        <div style={{background:"#dcfce7",borderRadius:10,padding:"10px",marginBottom:14}}>
          <p style={{color:"#166534",fontWeight:700,fontSize:"1rem",margin:0}}>+{scholar.pts} نقطة 🏅</p>
        </div>
        <button onClick={onClose} style={{width:"100%",background:"#1e3a5f",color:"white",border:"none",borderRadius:10,padding:"12px",fontSize:"0.95rem",cursor:"pointer",fontWeight:700,...F}}>
          رائع! استمر ←
        </button>
      </div>
    </div>
  );
}

function ScholarsPage({user,scholars}){
  const have=user?.scholars||[];
  return(
    <div>
      <div style={{...gW({padding:"14px 16px",marginBottom:16})}}>
        <div style={{display:"flex",justifyContent:"space-between",marginBottom:8}}>
          <p style={{fontWeight:700,color:"#1e3a5f",margin:0}}>🏛️ أعلام النحو</p>
          <span style={{background:"#fef3c7",color:"#92400e",borderRadius:20,padding:"3px 12px",fontSize:"0.8rem",fontWeight:700}}>{have.length}/{scholars.length} مكتشف</span>
        </div>
        <div style={{background:"#f1f5f9",borderRadius:8,height:8,overflow:"hidden"}}>
          <div style={{height:"100%",width:`${(have.length/scholars.length)*100}%`,background:"linear-gradient(90deg,#fbbf24,#f59e0b)",borderRadius:8,transition:"width 0.5s"}}/>
        </div>
      </div>
      <div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
        {scholars.map(s=>{const unlocked=have.includes(s.id);return(
          <div key={s.id} style={{...gW({padding:"13px 11px",opacity:unlocked?1:0.55,border:unlocked?"2px solid #fbbf24":"1.5px solid rgba(30,58,95,0.15)"})}}>
            <div style={{display:"flex",alignItems:"center",gap:8,marginBottom:5}}>
              <span style={{fontSize:"1.3rem",filter:unlocked?"none":"grayscale(1)"}}>{s.icon}</span>
              <div>
                <p style={{fontWeight:700,color:unlocked?"#92400e":"#94a3b8",margin:0,fontSize:"0.8rem"}}>{s.name}</p>
                <p style={{color:"#b45309",margin:0,fontSize:"0.67rem"}}>{s.era}</p>
              </div>
            </div>
            {unlocked
              ?<><p style={{color:"#1e3a5f",margin:0,fontSize:"0.72rem",fontWeight:600}}>{s.title}</p>
                <p style={{color:"#059669",margin:"3px 0 0",fontSize:"0.72rem",fontWeight:600}}>+{s.pts} نقطة ✓</p></>
              :<p style={{color:"#94a3b8",margin:0,fontSize:"0.73rem",fontStyle:"italic"}}>🔒 أكمل الجبل لاكتشافه</p>
            }
          </div>
        );})}
      </div>
    </div>
  );
}

// ─── عرض تقديمي PDF لكان وأخواتها ───
function PDFViewer(){
  const slides=[
    {type:"cover",
     title:"كان وأخواتها",
     subtitle:"النواسخ الفعلية — الدرس السادس",
     body:"كان وأخواتها أفعال ناقصة تدخل على الجملة الاسمية، فترفع المبتدأ ويُسمَّى اسمها، وتنصب الخبر ويُسمَّى خبرها. إذا تأمّلت الأمثلة وجدت أن كان وأخواتها دخلت على المبتدأ والخبر، فرفعت المبتدأ، ونصبت الخبر."},

    {type:"def",title:"ما عملُ كان؟",
     cards:[
       {h:"التعريف",b:"تدخل على المبتدأ فترفعه اسماً لها، وعلى الخبر فتنصبه خبراً لها"},
       {h:"المصطلح",b:"تُسمَّى أفعالاً ناقصة لأنها لا تكتفي بفاعلها، بل تحتاج خبراً منصوباً"},
       {h:"مثال",b:"كانَ التلميذُ مجتهداً ← التلميذُ: اسمها. مجتهداً: خبرها"}
     ],
     note:"قبل دخول كان: التلميذُ مجتهدٌ (مبتدأ وخبر مرفوعان). بعد دخولها: التلميذُ (مرفوع اسم كان) مجتهداً (منصوب خبر كان)."},

    {type:"list3",title:"أخوات كان الثلاثة عشر",
     items:[
       {n:"القسم الأول",h:"كانَ، أمسى، أصبحَ، أضحى، ظلَّ، باتَ، صارَ، ليسَ",b:"تعمل بدون شرط — ثمانية أفعال"},
       {n:"القسم الثاني",h:"زالَ، انفكَّ، فَتِئَ، بَرِحَ",b:"تعمل بشرط تقدّم نفي أو استفهام أو نهي"},
       {n:"القسم الثالث",h:"دامَ",b:"تعمل بشرط تقدّم ما المصدرية الظرفية"}
     ]},

    {type:"detail",title:"أقسام كان من حيث العمل",
     intro:"تنقسم كان وأخواتها من حيث عملها ثلاثة أقسام:",
     items:[
       {n:"1",h:"يعمل بدون شرط",b:"كانَ، أمسى، أصبحَ، أضحى، ظلَّ، باتَ، صارَ، ليسَ",ex:"كانَ الجوُّ حارّاً"},
       {n:"2",h:"يعمل بشرط النفي أو الاستفهام أو النهي",b:"زالَ، انفكَّ، فَتِئَ، بَرِحَ",ex:"ما زالَ الإسلامُ عظيماً"},
       {n:"3",h:"يعمل بشرط ما المصدرية الظرفية",b:"دامَ وحده",ex:"﴿ما دمتُ حيّاً﴾ — مدة دوامي"}
     ]},

    {type:"detail2",title:"أقسام كان من حيث التصرف",
     intro:"تنقسم كان وأخواتها من حيث التصرف ثلاثة أقسام:",
     items:[
       {n:"1",h:"متصرفة تصرفاً كاملاً (ماضي + مضارع + أمر)",ex:"كانَ / يكون / كُنْ — أمسى / يُمسي / أمسِ"},
       {n:"2",h:"متصرفة تصرفاً ناقصاً (ماضي + مضارع فقط)",ex:"زالَ / يزال — بَرِحَ / يبرح"},
       {n:"3",h:"جامدة تلزم صيغة الماضي",ex:"ليسَ — دامَ (لا مضارع ولا أمر)"}
     ],
     note:"الجامد يسمى 'فعلاً جامداً'. ليس ودام هما الفعلان الجامدان في هذا الباب."},

    {type:"cards3",title:"دلالات بعض أفعال كان وأخواتها",
     cards:[
       {icon:"🌅",h:"أصبحَ / أمسى / أضحى",ex:"تدل على أوقات اليوم: الصباح / المساء / الضحى"},
       {icon:"🌙",h:"باتَ / ظلَّ",ex:"باتَ: يدل على الليل. ظلَّ: يدل على النهار والاستمرار"},
       {icon:"🔄",h:"صارَ",ex:"تدل على التحوّل من حال إلى حال: صارَ الطالبُ طبيباً"}
     ]},

    {type:"match",title:"نماذج إعرابية",
     intro:"فيما يلي نماذج إعرابية من الجدول:",
     cards:[
       {h:"كانَ المطرُ غزيراً",ex:"كانَ: فعل ناقص. المطرُ: اسمها مرفوع. غزيراً: خبرها منصوب"},
       {h:"ليسَ الطالبُ مهملاً",ex:"ليسَ: فعل ناقص جامد. الطالبُ: اسمها مرفوع. مهملاً: خبرها منصوب"},
       {h:"ما زالَ الإسلامُ منتشراً",ex:"ما: نافية. زالَ: فعل ناقص. الإسلامُ: اسمها. منتشراً: خبرها"}
     ],
     note:"جميع أفعال كان وأخواتها تعمل نفس العمل: رفع الاسم ونصب الخبر."},

    {type:"list",title:"شروط عمل كل قسم",
     intro:"يُشترط لعمل بعض أفعال كان وأخواتها شروط محددة:",
     items:[
       {h:"زالَ، انفكَّ، فتئَ، بَرِحَ",ex:"تقول في زال: ما زال / هل زال / لا تَزَلْ"},
       {h:"دامَ",ex:"ما دامَ الأستاذُ شارحاً — ما: مصدرية ظرفية"},
       {h:"القسم الأول + ليس",ex:"لا يحتاج شرطاً — يعمل مطلقاً"}
     ]},

    {type:"summary",title:"الخلاصة",
     cards:[
       {h:"العمل",b:"كان وأخواتها ترفع المبتدأ اسماً لها، وتنصب الخبر خبراً لها"},
       {h:"العدد",b:"ثلاثة عشر فعلاً مقسّمة بحسب العمل والتصرف"},
       {h:"الإعراب",b:"الاسم مرفوع بما كان يُرفع به، والخبر منصوب بالفتحة أو نائبها"}
     ],
     note:"تذكّر: الفعل الناقص لا يكتفي بفاعله بل يحتاج إلى خبر منصوب ليتم معناه. ليس ودام جامدان لا يتصرفان."},
  ];

  const [slide,setSlide]=useState(0);
  const s=slides[slide];
  const renderSlide=()=>{
    const cardBg="#dbeafe";
    if(s.type==="cover")return(<div style={{minHeight:320,display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",textAlign:"center",padding:"20px 10px"}}><div style={{fontSize:"3rem",marginBottom:12}}>📕</div><h2 style={{fontSize:"1.8rem",fontWeight:800,color:"#1e293b",margin:"0 0 8px"}}>{s.title}</h2><h3 style={{fontSize:"1rem",fontWeight:700,color:"#dc2626",margin:"0 0 20px"}}>{s.subtitle}</h3><p style={{color:"#374151",fontSize:"0.88rem",lineHeight:1.9,maxWidth:380}}>{s.body}</p></div>);
    if(s.type==="def")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.5rem",fontWeight:800,color:"#1e293b",margin:"0 0 18px",textAlign:"right"}}>{s.title}</h2><div style={{display:"flex",flexDirection:"column",gap:10,marginBottom:14}}>{s.cards.map((c,i)=><div key={i} style={{background:cardBg,borderRadius:10,padding:"13px 16px"}}><p style={{fontWeight:700,color:"#1e293b",margin:"0 0 5px",fontSize:"0.92rem"}}>{c.h}</p><p style={{color:"#374151",fontSize:"0.85rem",margin:0}}>{c.b}</p></div>)}</div><p style={{color:"#374151",fontSize:"0.82rem",lineHeight:1.8}}>{s.note}</p></div>);
    if(s.type==="list3")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.5rem",fontWeight:800,color:"#1e293b",margin:"0 0 20px",textAlign:"right"}}>{s.title}</h2>{s.items.map((item,i)=><div key={i} style={{marginBottom:16,borderBottom:"2px solid #dc2626",paddingBottom:12}}><p style={{color:"#94a3b8",fontSize:"0.75rem",margin:"0 0 4px",textAlign:"left",fontWeight:600}}>{item.n}</p><h3 style={{fontWeight:800,color:"#dc2626",margin:"0 0 4px",fontSize:"0.95rem"}}>{item.h}</h3><p style={{color:"#64748b",margin:0,fontSize:"0.85rem"}}>{item.b}</p></div>)}</div>);
    if(s.type==="detail")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.4rem",fontWeight:800,color:"#1e293b",margin:"0 0 10px",textAlign:"right"}}>{s.title}</h2><p style={{color:"#374151",fontSize:"0.85rem",lineHeight:1.9,marginBottom:14}}>{s.intro}</p>{s.items.map((item,i)=><div key={i} style={{display:"flex",gap:10,marginBottom:12,alignItems:"flex-start"}}><div style={{width:32,height:32,borderRadius:7,background:cardBg,display:"flex",alignItems:"center",justifyContent:"center",fontSize:"0.9rem",fontWeight:800,color:"#dc2626",flexShrink:0}}>{item.n}</div><div style={{background:"#f8fafc",borderRadius:9,padding:"10px 13px",flex:1}}><p style={{fontWeight:700,color:"#1e293b",margin:"0 0 3px",fontSize:"0.88rem"}}>{item.h}</p><p style={{color:"#64748b",margin:"0 0 5px",fontSize:"0.82rem",lineHeight:1.7}}>{item.b}</p><p style={{color:"#dc2626",fontSize:"0.8rem",margin:0,fontWeight:600}}>مثال: {item.ex}</p></div></div>)}</div>);
    if(s.type==="detail2")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.4rem",fontWeight:800,color:"#1e293b",margin:"0 0 10px",textAlign:"right"}}>{s.title}</h2><p style={{color:"#374151",fontSize:"0.85rem",lineHeight:1.9,marginBottom:14}}>{s.intro}</p>{s.items.map((item,i)=><div key={i} style={{display:"flex",gap:10,marginBottom:10,alignItems:"center"}}><div style={{width:28,height:28,borderRadius:6,background:cardBg,display:"flex",alignItems:"center",justifyContent:"center",fontSize:"0.85rem",fontWeight:800,color:"#dc2626",flexShrink:0}}>{i+1}</div><div style={{background:"#f8fafc",borderRadius:9,padding:"9px 13px",flex:1}}><p style={{fontWeight:700,color:"#1e293b",margin:"0 0 3px",fontSize:"0.87rem"}}>{item.h}</p><p style={{color:"#dc2626",fontSize:"0.82rem",margin:0,fontWeight:600}}>{item.ex}</p></div></div>)}<p style={{color:"#374151",fontSize:"0.82rem",lineHeight:1.8,marginTop:8,borderTop:"1px solid #e2e8f0",paddingTop:10}}>{s.note}</p></div>);
    if(s.type==="cards3")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.4rem",fontWeight:800,color:"#1e293b",margin:"0 0 16px",textAlign:"right"}}>{s.title}</h2>{s.cards.map((c,i)=><div key={i} style={{background:cardBg,borderRadius:11,padding:"13px 15px",marginBottom:10,display:"flex",alignItems:"center",gap:11}}><div style={{width:40,height:40,borderRadius:"50%",background:"#dc2626",display:"flex",alignItems:"center",justifyContent:"center",fontSize:"1rem",flexShrink:0}}>{c.icon}</div><div><p style={{fontWeight:700,color:"#1e293b",margin:"0 0 3px",fontSize:"0.9rem"}}>{c.h}</p><p style={{color:"#374151",fontSize:"0.82rem",margin:0}}>{c.ex}</p></div></div>)}</div>);
    if(s.type==="match")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.4rem",fontWeight:800,color:"#1e293b",margin:"0 0 10px",textAlign:"right"}}>{s.title}</h2><p style={{color:"#374151",fontSize:"0.83rem",lineHeight:1.8,marginBottom:14}}>{s.intro}</p>{s.cards.map((c,i)=><div key={i} style={{background:"#fff1f2",border:"1.5px solid #fecdd3",borderRadius:9,padding:"11px 13px",marginBottom:9}}><p style={{fontWeight:700,color:"#1e293b",margin:"0 0 3px",fontSize:"0.87rem"}}>{c.h}</p><p style={{color:"#dc2626",fontSize:"0.82rem",margin:0}}>{c.ex}</p></div>)}<p style={{color:"#374151",fontSize:"0.8rem",lineHeight:1.8,marginTop:10}}>{s.note}</p></div>);
    if(s.type==="list")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.4rem",fontWeight:800,color:"#1e293b",margin:"0 0 10px",textAlign:"right"}}>{s.title}</h2><p style={{color:"#374151",fontSize:"0.83rem",lineHeight:1.8,marginBottom:12}}>{s.intro}</p>{s.items.map((item,i)=><div key={i} style={{display:"flex",gap:9,alignItems:"flex-start",marginBottom:9}}><div style={{width:7,height:7,borderRadius:"50%",background:"#dc2626",marginTop:6,flexShrink:0}}/><div><p style={{fontWeight:700,color:"#1e293b",margin:"0 0 2px",fontSize:"0.87rem"}}>{item.h}</p><p style={{color:"#dc2626",fontSize:"0.82rem",margin:0}}>{item.ex}</p></div></div>)}</div>);
    if(s.type==="summary")return(<div style={{padding:"10px 0"}}><h2 style={{fontSize:"1.4rem",fontWeight:800,color:"#1e293b",margin:"0 0 16px",textAlign:"right"}}>{s.title}</h2>{s.cards.map((c,i)=><div key={i} style={{background:cardBg,borderRadius:9,padding:"12px 15px",marginBottom:9}}><p style={{fontWeight:700,color:"#1e293b",margin:"0 0 4px",fontSize:"0.9rem"}}>{c.h}</p><p style={{color:"#374151",fontSize:"0.83rem",margin:0}}>{c.b}</p></div>)}<p style={{color:"#374151",fontSize:"0.83rem",lineHeight:1.9,marginTop:12}}>{s.note}</p></div>);
    return null;
  };
  return(
    <div style={{...gW({overflow:"hidden"})}}>
      <div style={{background:"#991b1b",padding:"12px 18px",display:"flex",justifyContent:"space-between",alignItems:"center"}}>
        <p style={{color:"white",margin:0,fontWeight:700,fontSize:"0.88rem"}}>📄 كان وأخواتها — العرض التقديمي</p>
        <span style={{color:"rgba(255,255,255,0.7)",fontSize:"0.78rem"}}>{slide+1}/{slides.length}</span>
      </div>
      <div style={{padding:"18px 20px",minHeight:300,direction:"rtl"}}>{renderSlide()}</div>
      <div style={{borderTop:"1.5px solid #e2e8f0",padding:"12px 18px",display:"flex",justifyContent:"space-between",alignItems:"center"}}>
        <button onClick={()=>setSlide(Math.max(0,slide-1))} disabled={slide===0} style={{...gS,opacity:slide===0?0.4:1,padding:"8px 18px",fontSize:"0.85rem"}}>→ السابق</button>
        <div style={{display:"flex",gap:5}}>{slides.map((_,i)=><div key={i} onClick={()=>setSlide(i)} style={{width:i===slide?22:8,height:8,borderRadius:4,background:i===slide?"#dc2626":"#cbd5e1",cursor:"pointer",transition:"all 0.3s"}}/>) }</div>
        <button onClick={()=>setSlide(Math.min(slides.length-1,slide+1))} disabled={slide===slides.length-1} style={{...gS,opacity:slide===slides.length-1?0.4:1,padding:"8px 18px",fontSize:"0.85rem"}}>التالي ←</button>
      </div>
    </div>
  );
}

function PodcastViewer(){
  const msgs=[
    {sp:"الأستاذ",t:"ما الفرق بين الفعل الناقص والفعل التام في باب كان وأخواتها؟",isT:true},
    {sp:"الطالب",t:"الفعل التام يكتفي بفاعله، أما الناقص فيحتاج إلى اسم وخبر ليتم معناه."},
    {sp:"الأستاذ",t:"أحسنت! كم عدد أفعال كان وأخواتها؟ وما أقسامها من حيث العمل؟",isT:true},
    {sp:"الطالب",t:"ثلاثة عشر فعلاً. القسم الأول يعمل بلا شرط، والثاني يحتاج نفياً أو استفهاماً أو نهياً، والثالث يحتاج ما المصدرية الظرفية."},
    {sp:"الأستاذ",t:"مثّل على القسم الثاني بجملة مفيدة.",isT:true},
    {sp:"الطالب",t:"ما زالَ الإسلامُ منتشراً — زال من القسم الثاني، وقد سبقتها ما النافية."},
    {sp:"الأستاذ",t:"ما الأفعال الجامدة في هذا الباب؟ ولماذا سُمِّيت جامدة؟",isT:true},
    {sp:"الطالب",t:"ليسَ ودامَ، لأنهما يلزمان صيغة الماضي ولا يأتي منهما مضارع ولا أمر."},
  ];
  return(<div style={{...gW({overflow:"hidden"})}}>
    <div style={{background:"linear-gradient(135deg,#7c3aed,#5b21b6)",padding:"18px",color:"white",textAlign:"center"}}><div style={{fontSize:"2.5rem",marginBottom:6}}>🎙️</div><h3 style={{margin:"0 0 3px",fontSize:"1rem"}}>البودكاست التعليمي</h3><p style={{opacity:0.8,margin:0,fontSize:"0.82rem"}}>حوار علمي: كان وأخواتها</p></div>
    <div style={{padding:18,maxHeight:"55vh",overflowY:"auto"}}>
      {msgs.map((m,i)=><div key={i} style={{display:"flex",gap:9,marginBottom:12,flexDirection:m.isT?"row":"row-reverse"}}><div style={{width:32,height:32,borderRadius:"50%",background:m.isT?"#dbeafe":"#dcfce7",display:"flex",alignItems:"center",justifyContent:"center",fontSize:"0.95rem",flexShrink:0}}>{m.isT?"👨‍🏫":"👨‍🎓"}</div><div style={{background:m.isT?"#eff6ff":"#f0fdf4",borderRadius:10,padding:"9px 13px",maxWidth:"78%"}}><p style={{fontSize:"0.7rem",color:m.isT?"#1d4ed8":"#166534",margin:"0 0 3px",fontWeight:600}}>{m.sp}</p><p style={{fontSize:"0.83rem",color:"#1e293b",margin:0,lineHeight:1.7}}>{m.t}</p></div></div>)}
      <div style={{background:"#f8fafc",borderRadius:9,padding:"10px",textAlign:"center"}}><p style={{color:"#94a3b8",fontSize:"0.76rem",margin:0}}>🎧 في النسخة الكاملة يُربط بملف صوتي حقيقي</p></div>
    </div>
  </div>);
}

function VideoViewer(){
  return(<div style={{...gW({overflow:"hidden"})}}>
    <div style={{background:"#dc2626",padding:"13px 18px",color:"white"}}><h3 style={{margin:0,fontSize:"0.92rem"}}>▶️ الفيديو التعليمي — يوتيوب</h3></div>
    <div style={{padding:18}}>
      <div style={{background:"#1a1a2e",borderRadius:10,aspectRatio:"16/9",display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",marginBottom:14}}><div style={{fontSize:"3.5rem"}}>▶️</div><p style={{color:"white",margin:"6px 0 0",fontSize:"0.88rem"}}>شرح درس كان وأخواتها</p></div>
      {["عمل كان وأخواتها — رفع الاسم ونصب الخبر","أقسام كان من حيث العمل — الثلاثة الأقسام","أقسام كان من حيث التصرف — الكامل والناقص والجامد","نماذج إعرابية تطبيقية على أفعال كان"].map((v,i)=><div key={i} style={{display:"flex",alignItems:"center",gap:10,padding:"9px 0",borderBottom:"0.5px solid #f1f5f9"}}><div style={{width:32,height:32,background:"#fee2e2",borderRadius:7,display:"flex",alignItems:"center",justifyContent:"center",fontSize:"0.85rem",flexShrink:0,color:"#dc2626"}}>▶</div><p style={{margin:0,fontSize:"0.83rem",color:"#1e293b"}}>{v}</p></div>)}
      <div style={{background:"#fef2f2",borderRadius:9,padding:"10px",marginTop:12,textAlign:"center"}}><p style={{color:"#94a3b8",fontSize:"0.76rem",margin:0}}>في النسخة الكاملة يُضاف رابط يوتيوب الحقيقي</p></div>
    </div>
  </div>);
}

function AdminDashboard(){
  const results=loadR(),users=loadU();
  const avg=results.length?(results.reduce((s,r)=>s+r.score,0)/results.length).toFixed(1):"—";
  const lb=getLeaderboard();
  const exportCSV=()=>{
    const h=["الاسم","الرقم","المستوى","العام","التاريخ","النتيجة","النقاط","الحالة","مواضيع ضعيفة","الوقت"];
    const rows=results.map(r=>[r.name,r.studentId||"—",r.level||"—",r.year||"—",r.date,`${r.score}/7`,r.earnedPts||0,r.completed?"أتمّ":"سقط",r.weakTopics,r.duration]);
    const csv="\uFEFF"+[h,...rows].map(r=>r.map(c=>`"${c}"`).join(",")).join("\n");
    const b=new Blob([csv],{type:"text/csv;charset=utf-8;"});
    const u=URL.createObjectURL(b);
    const a=document.createElement("a");a.href=u;a.download="نتائج_كان_وأخواتها.csv";document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(u);
  };
  return(<>
    <div style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:10,marginBottom:16}}>
      {[{l:"الطلاب",v:users.length},{l:"المحاولات",v:results.length},{l:"متوسط النتائج",v:`${avg}/7`}].map((s,i)=><div key={i} style={{...gW({padding:"13px 10px",textAlign:"center"})}}><p style={{color:"#94a3b8",fontSize:"0.7rem",margin:"0 0 3px"}}>{s.l}</p><p style={{color:"#1e293b",fontSize:"1.1rem",fontWeight:700,margin:0}}>{s.v}</p></div>)}
    </div>
    <div style={{...gW({padding:"14px 16px",marginBottom:14})}}>
      <p style={{fontWeight:700,color:"#1e3a5f",margin:"0 0 12px",fontSize:"0.9rem"}}>🏆 أفضل الطلاب نقاطاً</p>
      {lb.slice(0,5).map((s,i)=><div key={i} style={{display:"flex",alignItems:"center",gap:10,padding:"8px 0",borderBottom:"0.5px solid #f1f5f9"}}><span style={{fontSize:"1rem",width:24}}>{["🥇","🥈","🥉","4️⃣","5️⃣"][i]}</span><div style={{flex:1}}><p style={{fontWeight:600,color:"#1e293b",margin:0,fontSize:"0.85rem"}}>{s.name}</p><p style={{color:"#94a3b8",margin:0,fontSize:"0.72rem"}}>🏛️ {s.scholars.length}/{SCHOLARS.length} عالم</p></div><p style={{color:"#d97706",fontWeight:700,margin:0,fontSize:"0.95rem"}}>{s.pts.toLocaleString()} نقطة</p></div>)}
    </div>
    <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:10}}>
      <p style={{margin:0,fontWeight:600,color:"white",fontSize:"0.85rem",textShadow:"0 1px 4px rgba(0,0,0,0.5)"}}>سجل المحاولات ({results.length})</p>
      <button onClick={exportCSV} disabled={!results.length} style={{background:results.length?"#059669":"#94a3b8",color:"white",border:"none",borderRadius:7,padding:"7px 14px",fontSize:"0.8rem",cursor:results.length?"pointer":"not-allowed",...F,fontWeight:600}}>⬇ Excel</button>
    </div>
    {!results.length?<div style={{...gW({padding:"28px",textAlign:"center",color:"#94a3b8"})}}>لا توجد نتائج بعد</div>:(
      <div style={{...gW({overflow:"hidden"})}}>
        <div style={{overflowX:"auto"}}>
          <table style={{width:"100%",borderCollapse:"collapse",fontSize:"0.8rem",minWidth:540}}>
            <thead><tr style={{background:"#f8fafc",borderBottom:"1px solid #e2e8f0"}}>{["الاسم","الرقم","المستوى","النتيجة","النقاط","الحالة","ضعيف في"].map(h=><th key={h} style={{padding:"8px 9px",textAlign:"right",color:"#64748b",fontWeight:600}}>{h}</th>)}</tr></thead>
            <tbody>{results.slice().reverse().map((r,i)=>{const g=getGrade(r.score);return(<tr key={i} style={{borderBottom:"0.5px solid #f1f5f9",background:i%2===0?"white":"#fafafa"}}><td style={{padding:"7px 9px",fontWeight:600,color:"#1e293b"}}>{r.name}</td><td style={{padding:"7px 9px",color:"#64748b",fontSize:"0.76rem"}}>{r.studentId||"—"}</td><td style={{padding:"7px 9px",color:"#64748b",fontSize:"0.76rem"}}>{r.level||"—"}</td><td style={{padding:"7px 9px"}}><span style={{background:g.bg,color:g.c,borderRadius:7,padding:"2px 8px",fontWeight:700,fontSize:"0.78rem"}}>{r.score}/7</span></td><td style={{padding:"7px 9px",color:"#d97706",fontWeight:700}}>{r.earnedPts||0}</td><td style={{padding:"7px 9px",color:r.completed?"#166534":"#991b1b",fontSize:"0.78rem",fontWeight:600}}>{r.completed?"✅":"❌"}</td><td style={{padding:"7px 9px",color:"#92400e",fontSize:"0.74rem",maxWidth:120}}>{r.weakTopics}</td></tr>);})}</tbody>
          </table>
        </div>
      </div>
    )}
  </>);
}

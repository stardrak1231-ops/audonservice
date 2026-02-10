<?php
/**
 * หน้าแรก - Guest Homepage
 * ระบบจัดการอู่ซ่อมรถ
 */

require_once 'config/database.php';

// ดึงข้อมูลบริการจากฐานข้อมูล
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM service_items ORDER BY service_id LIMIT 15");
    $services = $stmt->fetchAll();

    // ดึงข่าวสาร/โปรโมชั่นที่ active
    $promoStmt = $pdo->query("SELECT * FROM promotions WHERE is_active = 1 AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY created_at DESC LIMIT 6");
    $promotions = $promoStmt->fetchAll();
} catch (Exception $e) {
    $services = [];
    $promotions = [];
}

$pageTitle = 'หน้าแรก';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="relative bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 text-white overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs>
                <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                    <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" />
                </pattern>
            </defs>
            <rect width="100" height="100" fill="url(#grid)" />
        </svg>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32 relative">
        <div class="text-center max-w-3xl mx-auto">
            <h1 class="text-4xl lg:text-5xl xl:text-6xl font-bold leading-tight mb-6">
                ดูแลรถของคุณ<br>
                <span class="text-blue-200">ด้วยมืออาชีพ</span>
            </h1>
            <p class="text-xl text-blue-100 mb-8 max-w-2xl mx-auto">
                บริการซ่อมบำรุงรถยนต์ครบวงจร ด้วยทีมช่างผู้เชี่ยวชาญ
                และอุปกรณ์ที่ทันสมัย พร้อมดูแลรถของคุณอย่างใส่ใจ
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="register.php"
                    class="bg-white text-blue-600 hover:bg-blue-50 px-8 py-4 rounded-xl font-semibold text-lg transition-all transform hover:scale-105 shadow-lg hover:shadow-xl inline-flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                        </path>
                    </svg>
                    สมัครสมาชิก
                </a>
                <a href="#services"
                    class="border-2 border-white text-white hover:bg-white hover:text-blue-600 px-8 py-4 rounded-xl font-semibold text-lg transition-all inline-flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                        </path>
                    </svg>
                    ดูบริการ
                </a>
            </div>
        </div>
    </div>


    <!-- Wave Decoration -->
    <div class="absolute bottom-0 left-0 right-0" style="line-height: 0; margin-bottom: -1px;">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"
            style="width: 100%; height: auto; display: block;">
            <path
                d="M0 120L60 105C120 90 240 60 360 45C480 30 600 30 720 37.5C840 45 960 60 1080 67.5C1200 75 1320 75 1380 75L1440 75V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z"
                fill="white" stroke="none" />
        </svg>
    </div>
</section>

<!-- Services Section -->
<section id="services" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-blue-600 font-semibold text-sm uppercase tracking-wider">บริการของเรา</span>
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mt-2 mb-4">บริการซ่อมบำรุงครบวงจร</h2>
            <p class="text-gray-600 max-w-2xl mx-auto">เรามีบริการหลากหลายเพื่อดูแลรถของคุณ ไม่ว่าจะเป็นงานซ่อม
                งานบำรุงรักษา หรืองานตรวจเช็คทั่วไป</p>
        </div>

        <div class="relative group/carousel">
            <!-- Navigation Buttons -->
            <button onclick="scrollServices(-1)"
                class="absolute left-0 top-1/2 -translate-y-1/2 -ml-4 lg:-ml-12 z-10 w-12 h-12 bg-white text-gray-800 rounded-full shadow-lg flex items-center justify-center opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 hover:bg-blue-600 hover:text-white transform hover:scale-110">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>

            <button onclick="scrollServices(1)"
                class="absolute right-0 top-1/2 -translate-y-1/2 -mr-4 lg:-mr-12 z-10 w-12 h-12 bg-white text-gray-800 rounded-full shadow-lg flex items-center justify-center opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 hover:bg-blue-600 hover:text-white transform hover:scale-110">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>

            <!-- Carousel Track -->
            <div id="servicesTrack"
                class="flex gap-6 overflow-x-auto snap-x snap-mandatory scrollbar-hide py-4 px-2 -mx-2 items-stretch"
                style="scrollbar-width: none; -ms-overflow-style: none;">
                <?php if (empty($services)): ?>
                    <!-- Default Services (when no data in database) -->
                    <div
                        class="min-w-[300px] md:min-w-[350px] flex-none snap-center group bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-blue-200">
                        <div
                            class="w-14 h-14 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">ซ่อมเครื่องยนต์</h3>
                        <p class="text-gray-600 mb-4">ซ่อมบำรุงเครื่องยนต์ทุกชนิด ด้วยอะไหล่แท้คุณภาพสูง</p>
                        <span class="text-blue-600 font-semibold">เริ่มต้น ฿500</span>
                    </div>

                    <div
                        class="min-w-[300px] md:min-w-[350px] flex-none snap-center group bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-blue-200">
                        <div
                            class="w-14 h-14 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">เปลี่ยนถ่ายน้ำมัน</h3>
                        <p class="text-gray-600 mb-4">เปลี่ยนถ่ายน้ำมันเครื่องทุกประเภท พร้อมตรวจเช็คฟรี</p>
                        <span class="text-blue-600 font-semibold">เริ่มต้น ฿300</span>
                    </div>

                    <div
                        class="min-w-[300px] md:min-w-[350px] flex-none snap-center group bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-blue-200">
                        <div
                            class="w-14 h-14 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">ตรวจเช็คสภาพ</h3>
                        <p class="text-gray-600 mb-4">ตรวจเช็คสภาพรถยนต์ครบ 40 รายการ พร้อมรายงานผล</p>
                        <span class="text-blue-600 font-semibold">เริ่มต้น ฿200</span>
                    </div>

                    <div
                        class="min-w-[300px] md:min-w-[350px] flex-none snap-center group bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-blue-200">
                        <div
                            class="w-14 h-14 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">ระบบเบรค</h3>
                        <p class="text-gray-600 mb-4">ซ่อมและเปลี่ยนอะไหล่ระบบเบรค มั่นใจทุกการเดินทาง</p>
                        <span class="text-blue-600 font-semibold">เริ่มต้น ฿800</span>
                    </div>

                    <div
                        class="min-w-[300px] md:min-w-[350px] flex-none snap-center group bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-blue-200">
                        <div
                            class="w-14 h-14 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">ระบบไฟฟ้า</h3>
                        <p class="text-gray-600 mb-4">ซ่อมระบบไฟฟ้า แบตเตอรี่ และระบบสตาร์ท</p>
                        <span class="text-blue-600 font-semibold">เริ่มต้น ฿400</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <div
                            class="min-w-[300px] md:min-w-[350px] flex-none snap-center group bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-blue-200 flex flex-col h-full">
                            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 transition-colors">
                                <span class="text-2xl">⚙️</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-3">
                                <?php echo htmlspecialchars($service['service_name']); ?>
                            </h3>
                            <p class="text-gray-600 mb-4 flex-grow">เวลาโดยประมาณ:
                                <?php echo $service['estimated_minutes'] ?? '-'; ?> นาที
                            </p>
                            <span class="text-blue-600 font-semibold mt-auto">฿
                                <?php echo number_format($service['standard_price'] ?? 0, 0); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function scrollServices(direction) {
            const track = document.getElementById('servicesTrack');
            const scrollAmount = 350 + 24; // Card width (approx) + gap
            track.scrollBy({
                left: direction * scrollAmount,
                behavior: 'smooth'
            });
        }
    </script>
</section>

<!-- Promotions Section -->
<section id="promotions" class="py-20 bg-gradient-to-br from-blue-50 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-blue-600 font-semibold text-sm uppercase tracking-wider">โปรโมชั่น</span>
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mt-2 mb-4">ข่าวสารและโปรโมชั่น</h2>
            <p class="text-gray-600 max-w-2xl mx-auto">โปรโมชั่นพิเศษสำหรับลูกค้า อย่าพลาดโอกาสดีๆ จากเรา</p>
        </div>

        <?php if (empty($promotions)): ?>
            <div class="text-center text-gray-400 py-12">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z">
                    </path>
                </svg>
                <p>ยังไม่มีโปรโมชั่นในขณะนี้</p>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($promotions as $promo): ?>
                    <?php
                    $colors = $promo['promo_type'] === 'promotion'
                        ? ['from-orange-500', 'to-orange-700', 'bg-orange-100', 'text-orange-600']
                        : ['from-blue-500', 'to-blue-700', 'bg-blue-100', 'text-blue-600'];
                    ?>
                    <div
                        class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group">
                        <?php if ($promo['image_url']): ?>
                            <div style="height: 192px; overflow: hidden; border-radius: 16px 16px 0 0;">
                                <img src="<?php echo htmlspecialchars($promo['image_url']); ?>"
                                    alt="<?php echo htmlspecialchars($promo['title']); ?>"
                                    style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        <?php else: ?>
                            <div
                                class="h-48 bg-gradient-to-br <?php echo $colors[0]; ?> <?php echo $colors[1]; ?> flex items-center justify-center">
                                <span class="text-6xl"><?php echo $promo['promo_type'] === 'promotion' ? '🎉' : '📰'; ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="p-6">
                            <div class="flex items-center gap-2 mb-3">
                                <span
                                    class="<?php echo $colors[2]; ?> <?php echo $colors[3]; ?> text-xs font-semibold px-2 py-1 rounded-full">
                                    <?php echo $promo['promo_type'] === 'promotion' ? 'โปรโมชั่น' : 'ข่าวสาร'; ?>
                                </span>
                                <?php if ($promo['end_date']): ?>
                                    <span class="text-gray-500 text-sm">หมดเขต
                                        <?php echo date('d/m/Y', strtotime($promo['end_date'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($promo['title']); ?>
                            </h3>
                            <p class="text-gray-600 line-clamp-2"><?php echo htmlspecialchars($promo['content']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- About Section -->
<section id="about" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-blue-600 font-semibold text-sm uppercase tracking-wider">เกี่ยวกับเรา</span>
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mt-2 mb-4">ที่ตั้งและเวลาทำการ</h2>
        </div>

        <div class="grid lg:grid-cols-2 gap-8 items-stretch">
            <!-- Google Map -->
            <div class="rounded-2xl overflow-hidden shadow-lg h-[400px]">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3779.413774021305!2d98.95668947496732!3d18.690284482436546!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3125ecff227f%3A0xb7115589e182b1c3!2z4Lit4Li54LmIIOC4reC4uOC4lOC4o-C5gOC4i-C4reC4o-C5jOC4p-C4tOC4qg!5e0!3m2!1sth!2sth!4v1768313802625!5m2!1sth!2sth"
                    width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" class="w-full h-full">
                </iframe>
            </div>

            <!-- Business Hours -->
            <div
                class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-8 text-white shadow-lg flex flex-col justify-center">
                <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    เวลาทำการ
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-blue-500/30">
                        <div class="flex items-center gap-3">
                            <span class="w-3 h-3 bg-green-400 rounded-full"></span>
                            <span class="text-lg">จันทร์ - เสาร์</span>
                        </div>
                        <span class="font-semibold text-lg">08:00 - 17:00</span>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-3 h-3 bg-red-400 rounded-full"></span>
                            <span class="text-lg">อาทิตย์</span>
                        </div>
                        <span class="font-semibold text-lg">ปิดทำการ</span>
                    </div>
                </div>

                <div class="mt-8 p-5 bg-white/10 rounded-xl">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-blue-200">โทรนัดหมายล่วงหน้า</p>
                            <p class="text-2xl font-bold">089-953-0201</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-5 bg-white/10 rounded-xl">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-blue-200">ที่อยู่</p>
                            <p class="font-semibold">ตำบล บ้านแหวน อำเภอหางดง เชียงใหม่ 50230</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
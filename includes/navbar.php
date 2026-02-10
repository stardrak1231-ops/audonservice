<!-- Navigation Bar -->
<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="index.php" class="flex items-center space-x-2">
                    <div
                        class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-gray-800">อู่อุดร<span
                            class="text-blue-600">Service</span></span>
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php" class="text-gray-600 hover:text-blue-600 font-medium transition-colors">หน้าแรก</a>
                <a href="#services" class="text-gray-600 hover:text-blue-600 font-medium transition-colors">บริการ</a>
                <a href="#promotions"
                    class="text-gray-600 hover:text-blue-600 font-medium transition-colors">โปรโมชั่น</a>
                <a href="#about"
                    class="text-gray-600 hover:text-blue-600 font-medium transition-colors">เกี่ยวกับเรา</a>
            </div>

            <!-- Auth Buttons -->
            <div class="hidden md:flex items-center space-x-4">
                <a href="login.php"
                    class="text-gray-600 hover:text-blue-600 font-medium transition-colors">เข้าสู่ระบบ</a>
                <a href="register.php"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg font-medium transition-all transform hover:scale-105 shadow-md hover:shadow-lg">
                    สมัครสมาชิก
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden flex items-center">
                <button id="mobile-menu-btn" class="text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
        <div class="px-4 py-3 space-y-3">
            <a href="index.php" class="block text-gray-600 hover:text-blue-600 font-medium">หน้าแรก</a>
            <a href="#services" class="block text-gray-600 hover:text-blue-600 font-medium">บริการ</a>
            <a href="#promotions" class="block text-gray-600 hover:text-blue-600 font-medium">โปรโมชั่น</a>
            <a href="#about" class="block text-gray-600 hover:text-blue-600 font-medium">เกี่ยวกับเรา</a>
            <hr class="my-2">
            <a href="login.php" class="block text-gray-600 hover:text-blue-600 font-medium">เข้าสู่ระบบ</a>
            <a href="register.php"
                class="block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium text-center">
                สมัครสมาชิก
            </a>
        </div>
    </div>
</nav>

<script>
    // Mobile Menu Toggle
    document.getElementById('mobile-menu-btn').addEventListener('click', function () {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    });
</script>
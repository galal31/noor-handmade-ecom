<footer class="footer bg-dark text-white pt-5 pb-4 mt-auto">
    <div class="container text-center text-md-end">
        <div class="row">
            <div class="col-md-4 col-lg-4 col-xl-4 mx-auto mt-3">
                <h6 class="text-uppercase mb-4 fw-bold" style="color: var(--primary-color);">Noor Handmade</h6>
                <p>متجر متخصص في بيع المنتجات اليدوية المصنوعة بحب وشغف. كل قطعة لدينا تحكي قصة فريدة من الإبداع.</p>
            </div>

            <div class="col-md-4 col-lg-2 col-xl-2 mx-auto mt-3">
                <h6 class="text-uppercase mb-4 fw-bold">روابط سريعة</h6>
                <p><a href="products.php" class="text-white text-decoration-none">المنتجات</a></p>
                <p><a href="track_order.php" class="text-white text-decoration-none">تتبع طلبك</a></p>
                <p><a href="#" class="text-white text-decoration-none">سياسة الخصوصية</a></p>
            </div>

            <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mt-3">
                <h6 class="text-uppercase mb-4 fw-bold">تواصل معنا</h6>
                <p><i class="fas fa-home me-3"></i> القاهرة, مصر</p>
                <p><i class="fas fa-phone me-3"></i> 01150926556</p>
                <p><i class="fab fa-whatsapp me-3"></i> 
                    <a href="https://wa.me/201150926556" target="_blank" class="text-white text-decoration-none">راسلنا عبر واتساب</a>
                </p>
            </div>
        </div>

        <hr class="my-4">

        <div class="text-center">
            <p class="mb-1" style="font-size: 13px;">
                Designed by <span class="fw-bold">Galal Nasser</span>
            </p>
            <p style="font-size: 12px;">
                <a href="https://wa.me/201090317928" target="_blank" class="text-white text-decoration-none">
                    <i class="fab fa-whatsapp me-1"></i> Contact Me
                </a>
            </p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.1/nouislider.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        
        const cardSwiper = new Swiper('.product-card-slider', {
            loop: true,
            autoplay: { delay: 3000, disableOnInteraction: false },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        });

        const priceSlider = document.getElementById('price-slider');
        if (priceSlider) {
            const minPriceInput = document.getElementById('min-price-input');
            const maxPriceInput = document.getElementById('max-price-input');
            const priceDisplay = document.getElementById('price-slider-display');

            const minLimit = <?= json_encode($min_price_limit ?? 0) ?>;
            const maxLimit = <?= json_encode($max_price_limit ?? 1000) ?>;
            const currentMin = <?= json_encode($min_price ?? $min_price_limit ?? 0) ?>;
            const currentMax = <?= json_encode($max_price ?? $max_price_limit ?? 1000) ?>;

            noUiSlider.create(priceSlider, {
                start: [currentMin, currentMax],
                connect: true,
                step: 10,
                range: { 'min': minLimit, 'max': maxLimit },
                format: {
                    to: function (value) { return Math.round(value); },
                    from: function (value) { return Number(value); }
                }
            });

            priceSlider.noUiSlider.on('update', function (values) {
                const [minVal, maxVal] = values;
                priceDisplay.innerHTML = `${minVal} جنيه - ${maxVal} جنيه`;
                minPriceInput.value = minVal;
                maxPriceInput.value = maxVal;
            });
        }
        

        const isUserLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
        
        // --- الكود الجديد ---
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault(); // لمنع أي سلوك افتراضي للزر
                
                if (!isUserLoggedIn) {
                    Swal.fire({
                        title: 'يرجى تسجيل الدخول',
                        text: "يجب عليك تسجيل الدخول أولاً لتتمكن من إضافة المنتجات إلى السلة.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary-color)',
                        cancelButtonColor: '#aaa',
                        confirmButtonText: 'تسجيل الدخول',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'login.php';
                        }
                    });
                    return; // إيقاف التنفيذ
                }

                const productId = this.dataset.productId;
                const addButton = this;
                const Toast = Swal.mixin({ toast: true, position: 'top-start', showConfirmButton: false, timer: 2500 });

                addButton.disabled = true;

                fetch('cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ action: 'add', product_id: productId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('cart-counter').textContent = data.cart_total_items;
                        Toast.fire({ icon: 'success', title: data.message });
                    } else {
                        Toast.fire({ icon: 'error', title: data.message || 'حدث خطأ ما.' });
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    Toast.fire({ icon: 'error', title: 'حدث خطأ في الشبكة.' });
                })
                .finally(() => {
                    addButton.disabled = false;
                });
            });
        });
        
    });
</script>

</body>
</html>

<!-- FOOTER -->
    <footer id="footer">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <h3 class="mb-3 font-weight-bold text-white">Spirited Finds</h3>
                    <p>Delivering authentic and magical Studio Ghibli collectibles, apparel, and merchandise directly to true fans worldwide.</p>
                    <p class="mt-3"><i class="fas fa-location-dot mr-2"></i> Pasig City, Pag-asa, Philippines</p>
                    <p><i class="fas fa-phone mr-2"></i> +63 912 345 6789</p>
                    <p><i class="fas fa-envelope mr-2"></i> support@spiritedfinds.com</p>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                    <h4 class="mb-3 text-white font-weight-bold">Useful Links</h4>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php">Home</a></li>
                        <li class="mb-2"><a href="index.php#about">About Us</a></li>
                        <li class="mb-2"><a href="index.php#contact">Contact</a></li>
                        <li class="mb-2"><a href="customer/cart.php">Shopping Cart</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h4 class="mb-3 text-white font-weight-bold">Categories</h4>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="category1.php">Enchanted Pages</a></li>
                        <li class="mb-2"><a href="category2.php">Spirit Threads</a></li>
                        <li class="mb-2"><a href="category3.php">Cuddly Companions</a></li>
                        <li class="mb-2"><a href="category4.php">Magic Trinkets</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h4 class="mb-3 text-white font-weight-bold">Follow Us</h4>
                    <div class="d-flex gap-3">
                        <a href="#" class="nav-icon-btn mr-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="nav-icon-btn mr-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="nav-icon-btn"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date("Y"); ?> <strong>Spirited Finds</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- CART POP-OUT DRAWER -->
    <div class="cart-container" id="cart">
        <div class="cart-header">
            <h4 class="m-0 font-weight-bold" style="color: var(--ghibli-forest);">Shopping Cart</h4>
            <span class="close-cart">&times;</span>
        </div>
        <div class="cart-items flex-grow-1 overflow-auto my-3">
            <div class="d-flex align-items-center mb-3 p-2 rounded" style="background: var(--ghibli-warm-paper);">
                <img src="<?php echo get_image_path('Ghibli Studio Tote Bag.png'); ?>" alt="Tote Bag" width="60" class="rounded mr-3">
                <div>
                    <h6 class="m-0 font-weight-bold">Ghibli Studio Tote Bag</h6>
                    <small class="text-muted">1 x ₱350.00</small>
                </div>
            </div>
        </div>
        <div class="cart-footer">
            <div class="d-flex justify-content-between mb-3">
                <strong>Total:</strong>
                <strong style="color: var(--ghibli-terracotta);">₱350.00</strong>
            </div>
            <a href="customer/checkout.php" class="btn-ghibli-primary btn-block text-center">Checkout Now</a>
        </div>
    </div>

    <!-- Scroll To Top Button -->
    <a href="#" class="arrow-top"><i class="fas fa-chevron-up"></i></a>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        AOS.init({ duration: 900, once: true });

        // Cart Drawer Toggle
        $('.toggle-cart').on('click', function() {
            $('#cart').toggleClass('active');
        });
        $('.close-cart').on('click', function() {
            $('#cart').removeClass('active');
        });
    </script>
</body>
</html>
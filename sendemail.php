 <?php defined('ABSPATH') or die(); ?>



<?php
if (class_exists('WooCommerce')) {
    global $wpdb;

    $currentUrl = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $allowedUrl = "https://store.exportech.com.pt/configurador";

    if ($currentUrl === $allowedUrl || $currentUrl === "https://store.exportech.com.pt/configurador/") {


        $cached_products = get_transient('cached_woocommerce_products');
        if ($cached_products === false) {
            $sql = "
        SELECT 
            p.ID, 
            p.post_title,  
            p.post_content, 
            pm_price.meta_value AS price, 
            pm_sku.meta_value AS sku, 
            pm_image.meta_value AS thumbnail_id,
            pm_stock.meta_value AS stock_at_locations
        FROM {$wpdb->posts} AS p
        LEFT JOIN {$wpdb->prefix}postmeta AS pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_regular_price'
        LEFT JOIN {$wpdb->prefix}postmeta AS pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
        LEFT JOIN {$wpdb->prefix}postmeta AS pm_image ON p.ID = pm_image.post_id AND pm_image.meta_key = '_thumbnail_id'
        LEFT JOIN {$wpdb->prefix}postmeta AS pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock_at_locations'
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish'
        LIMIT 10000
    ";


            $products = $wpdb->get_results($sql);

            $output = [];
            foreach ($products as $product) {
                $formatted_price = number_format($product->price, 2, ',', '.');
                $terms = wp_get_post_terms($product->ID, 'product_cat');
                $categories = [];

                foreach ($terms as $term) {
                    $categories[] = $term->name;
                }

                $output[] = [
                    'name' => $product->post_title,
                    'description' => wp_trim_words($product->post_content, 30, '...'),
                    'price_with_coin' => "<span class=\"woocommerce-Price-amount amount\"><bdi>{$formatted_price}&nbsp;<span class=\"woocommerce-Price-currencySymbol\">&euro;</span></bdi></span>",
                    'price_without_coin' => $product->price,
                    'id' => $product->ID,
                    'sku' => $product->sku,
                    'link' => home_url('/?p=' . $product->ID),
                    'image_url' => wp_get_attachment_url($product->thumbnail_id),
                    'categories' => $categories,
                    'central' => get_post_meta($product->ID, 'wcmlim_stock_at_871', true),
                    'lisboa' => get_post_meta($product->ID, 'wcmlim_stock_at_869', true),
                    'funchal' => get_post_meta($product->ID, 'wcmlim_stock_at_870', true),
                ];
            }

            set_transient('cached_woocommerce_products', $output, 10800);
        } else {
            $output = $cached_products;
        }

    }
    ?>


<script>
// Converte a variável PHP $output para JSON e a passa para o JavaScript
let products = <?php echo json_encode($output); ?>;

// Armazena os produtos no localStorage
localStorage.setItem("products", JSON.stringify(products));
</script>



<?php
} else {
    echo json_encode(['error' => 'WooCommerce is not active.']);
}

if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    $user_meta = get_user_meta($current_user->ID);
    $user_email = esc_js($current_user->user_email);
    $user_name = esc_js($current_user->display_name);
    $gestor_contas = isset($user_meta['user_registration_hidden_1715342031'][0])
        ? esc_js($user_meta['user_registration_hidden_1715342031'][0])
        : null;
    ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    localStorage.setItem("userEmail", "<?php echo $user_email; ?>");
    localStorage.setItem("userName", "<?php echo $user_name; ?>");
    localStorage.setItem("gestorContas", "<?php echo $gestor_contas; ?>");
});
</script>
<?php } ?>



<script>
document.addEventListener("DOMContentLoaded", function() {
    if (document.querySelectorAll("#addAllToCart").length > 0) {
        let button = document.getElementById("addAllToCart");
        let originalText = button.innerHTML;

        button.addEventListener("click", function() {
            let cartProducts = JSON.parse(localStorage.getItem("cartProducts")) || [];
            let index = 0;

            if (cartProducts.length === 0) {
                toastr.warning("Nenhum produto no carrinho!");
                return;
            }

            button.innerHTML = `<span class="loader"></span> Adicionando...`;
            button.disabled = true;
            button.style.backgroundColor = "#007bff";

            function addProduct() {
                if (index < cartProducts.length) {
                    let {
                        id,
                        quantity
                    } = cartProducts[index];

                    if (!id || !quantity || isNaN(id) || isNaN(quantity) || quantity < 1) {
                        index++;
                        addProduct();
                        return;
                    }

                    let fetchPromises = [];
                    for (let i = 0; i < quantity; i++) {
                        fetchPromises.push(
                            fetch("<?php echo esc_url(home_url('/')); ?>?add-to-cart=" + id, {
                                method: "GET"
                            })
                        );
                    }

                    Promise.all(fetchPromises)
                        .then(responses => {
                            if (responses.every(response => response.ok)) {
                                index++;
                                addProduct();
                            } else {
                                throw new Error("Erro ao adicionar produto ID: " + id);
                            }
                        })
                        .catch(error => {
                            button.innerHTML = "Tentar novamente";
                            button.style.backgroundColor = "#dc3545";
                            button.disabled = false;
                        });
                } else {
                    toastr.success("Produtos adicionados ao carrinho!");
                    button.innerHTML = originalText;
                    button.disabled = false;
                    jQuery(document.body).trigger("wc_fragment_refresh");
                }
            }

            addProduct();
        });

    }
});
</script>



<?php
$is_logged_in = is_user_logged_in() ? 'true' : 'false';
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var isLoggedIn = <?php echo $is_logged_in; ?>;

    var configContainer = document.querySelector(".config-container");
    var notFounded = document.querySelector(".not-founded");

    if (isLoggedIn) {
        // If logged in, remove ".not-founded" and add "active" to ".config-container"
        if (notFounded) notFounded.remove();
        if (configContainer) configContainer.classList.add("active");
    } else {
        // If not logged in, remove ".config-container" and add "active" to ".not-founded"
        if (configContainer) configContainer.remove();
        if (notFounded) notFounded.classList.add("active");
    }
});
</script>





<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,100..900;1,100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Unbounded:wght@200..900&display=swap"
    rel="stylesheet">
<style>
body .nasa-product-stock-progress-bar.primary-bg {
    display: none !important;
}

body .stock-sold {
    display: none !important;
}

body .config-container.hidden {
    display: none !important;
}

body .branco .countdown-period {
    color: white !important;
}

body .nasa-slick-slider.nasa-not-elementor-style.slick-initialized.slick-slider img {
    transform: scale(0.8) !important;
}


body #mini-banner .elementor-widget-wrap.elementor-element-populated {
    max-width: 1400px !important;
    margin: 0px auto;
}


body .config-banner {
    width: 100% !important;
    margin-top: 30px !important;
}

body .not-founded.active,
body .config-container.active {
    display: flex !important;
}

body .countdown-section .countdown-amount {
    background: #ffff !important;
    border: 1px solid #DEE2E6 !important;
}


/* Loader animado */
.loader {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid white;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-right: 5px;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

body button#moveToTop {
    width: 40px;
    height: 40px;
    min-height: 40px;
    max-height: 40px;
    max-width: 40px;
    border-radius: 100%;
    min-width: 40px;
    background: red;
    position: fixed;
    right: 120px !important;
    bottom: 55px !important;
    border: 2px solid #ffff;
    background-color: #ffc107 !important;
    color: #fff;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.1);
    padding: 0px !important;
}


body .ur-frontend-form.login-registration p a {
    text-decoration: none !important;
    color: #000 !important;
    cursor: unset !important;
}


body .config-container {
    position: relative;
}


body .config-container small,
body .config-container .config-canvas .line h3,
body .config-container .cart-items p {
    text-transform: uppercase !important;
}



body .config-container #start-panel {
    position: absolute;
    top: 0px;
    left: 0px;
    width: 100%;
    height: 100%;
    z-index: 200;
    padding: 10px 20px;
    background: URL("https://i.ibb.co/xtjqqfGM/hex-background-networking-1.png");
    background-repeat: no-repeat;
    background-size: cover;
    background-color: #ffff;
    background-position: right;
    display: flex;
    align-items: center;
    justify-content: center;
}


body .mini-cart-sku {
    text-transform: uppercase !important;
}


body .config-container #start-panel img {
    object-fit: contain !important;
    max-width: 600px;
    min-width: 400px;
    margin-right: 20px;
}

body .config-container #start-panel .space-el {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-align: left;
    width: 100%;
    max-width: 1300px;
}




body .config-container #start-panel h1 {
    max-width: 600px;
    margin: 15px 0px;
    font-size: 40px;
    font-family: "Unbounded", sans-serif !important;
}



body .config-container .main-product-box .line h3 {
    z-index: 100 !important;
}



body .ur-frontend-form.login-registration .ur-form-row .button {
    color: #fff !important;
    cursor: pointer !important;
}

body .show-password-input {
    display: none !important;
}




body .config-container {
    text-transform: lowercase;
}

body .not-founded {
    display: none;
    align-items: center;
    justify-content: center;
    text-align: center;
    width: 100%;
    padding: 200px 20px;
    top: 0px;
    left: 0px;
    z-index: 100;
}

body #filter-components-input {
    display: none !important;
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,100..900;1,100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
    rel="stylesheet">
<style>
html,
body,
div,
body p,
body a,
body span,
body strong,
body h1,
body h2,
body h3,
body h4,
body h5,
body h6 {
    font-family: "Jost", sans-serif !important;
}




body .elementor-section.elementor-top-section.elementor-element.elementor-element-50e11db6 .item-product-widget {}





body.home .elementor-element.elementor-element-440282d {
    margin-top: 0px !important;
}

body #sendEmailBtn {
    border: none !important;
}

body .col-sidebar .widget-title {
    font-size: 125% !important;
}


body .col-sidebar .widget {
    margin-bottom: 0px !important;
}

body .config-container .flex-items .cart-items {
    padding-left: 0px !important;
}



body .config-container .flex-items .cart-items #sendEmailBtn {
    font-size: 10px !important;
}



body .filters-list .toggle-header .fa-solid {
    font-family: "Font Awesome 6 Free" !important;
}

body .config-container .cart-items ul .image {
    min-width: 70px !important;
    max-width: 70px !important;
}

body .config-container .cart-items li .fa-solid {
    color: #0084ff !important;
}



body .config-container .filters-list li ol {
    padding: 0px !important;
    margin: 0px !important;
}


body .config-container .filters-list li strong {
    margin: 0px !important;
}



body .config-container .cart-items .center button.btn-config-cart {
    font-size: 10px !important;
}

body .nasa-product-status-widget .nasa-filter-status:before {
    background: #ffff;
    border-color: #BBC1E1 !important;
}


body .wcapf-form.wcapf-form-20831 {}

body .wcapf-form.wcapf-form-20831 p:last-child {
    display: none !important;
}

body .flex-item-config {
    display: flex;
    align-items: center;
}

body .flex-item-config small {
    font-weight: bold !important;
    margin: 0px;
}

body .flex-item-config p {
    margin-left: 10px;
    font-size: 16px;
    font-weight: 700 !important;
}

body .product-page .nasa-panel.active {
    padding: 0px 15px !important;
}




mobile-navigation li.current-menu-item>a {
    color: red !important;
}

.parent-active {
    font-weight: bold;
    color: #005580 !important;
    /* Cor para os pais */
}

.menu-open>.nav-dropdown-mobile,
.menu-open>.sub-menu {
    display: block !important;
}

.nasa-classic-style li.active a,
.nasa-classic-style li:hover a {
    font-weight: bold;
    outline-color: white;
}

body #news-products .nasa-classic-style.nasa-tabs-has-bg.nasa-tabs-bg-transparent,
body #featured-products .nasa-classic-style.nasa-tabs-has-bg.nasa-tabs-bg-transparent {
    background-color: #F3F6FD !important;
    padding: 20px !important;

}

body #news-products .nasa-classic-style.nasa-tabs-has-bg.nasa-tabs-bg-transparent,
body #featured-products .nasa-classic-style.nasa-tabs-has-bg.nasa-tabs-bg-transparent {
    background-color: #F3F6FD !important;
    padding: 20px !important;
    border-radius: 6px !important;
}

body #news-products .nasa-dft.nasa-title,
body #featured-products .nasa-dft.nasa-title {
    left: 20px !important;
    top: 5px;
}


body #news-products .nasa-dft.nasa-title h3,
body #featured-products .nasa-dft.nasa-title h3 {
    font-weight: 550 !important;
}



body #news-products .nasa-tab a,
body #featured-products .nasa-tab a {
    text-transform: uppercase !important;
    font-size: 14px !important;
}



body .category-page .nasa-content-page-products.nasa-modern-5 ul.products {
    display: flex;
    width: 100%;
    flex-wrap: wrap;
}

.tt-menu {
    max-height: 530px;
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid #ddd;
    padding-right: 10px;
}

.tt-menu {
    scroll-behavior: smooth;
}




body .nasa-products-page-wrap.large-9 .products.grid li.product-category.product.product-warp-item {
    background-color: #ffff !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.03);
    padding: 20px 10px;
    border-radius: 10px !important;
    width: 24%;
}


body .elementor-element-7954d0a .ns-type-carousel .product-img-wrap {
    background: #ffff !important;
    border-top-right-radius: 10px !important;
    border-top-left-radius: 10px !important;
}

body .nasa-recommend-product .nasa-heading-title {
    text-transform: Capitalize !important;
}


body .badge.out-of-stock-label,
body .nasa-table-compare .weight,
body .nasa-table-compare .dimensions,
body .nasa-table-compare .description,
body .nasa-add-to-cart-fixed .woocommerce-info,
body .calculated_shipping .woocommerce-shipping-destination,
body .calculated_shipping .woocommerce-shipping-calculator,
body .ns_carousel_pro_checkout,
body .nasa-change-layout.grid-3,
body .nasa-change-layout.grid-6,
body .checkout-modern-left-wrap .mobile-text-center.margin-bottom-30.mobile-margin-bottom-0,
body .li-toggle-sidebar,
body #nasa-init-viewed,
body .product .button.product_type_simple:last-child,
body .product-warp-item .product .button,
body #nasa-back-to-top,
body .woocommerce-MyAccount-navigation .wcmamtx_intro_text,
body .cky-revisit-bottom-left {
    display: none !important;
}

.cky-btn-customize {
    color: white !important;
}

.cky-btn-reject {
    color: white !important;
}

body .product .product-img-wrap .button.product_type_simple {
    display: block !important;
}

body .product .product-img-wrap {
    display: block !important;
}

body .product .product-img-wrap img {
    min-height: 100% !important;
    height: 100% !important;
}

body .nasa-product-details-page .nasa-col-flex.nasa-relative {
    padding: 20px;
    min-height: 248px;
}

body .product-warp-item .product .nasa-group-btns .button {
    display: block !important;
}

header .nasa-icon-filter-cat {
    display: none !important;
}

body .nasa-flex-item-1-2-0 {
    width: 550px !important;
}

body .menu-sec ul {
    display: flex !important;
    align-items: center;
}

body .slick-list .slick-track .product-item .nasa-sku span,
body div .product-warp-item .nasa-sku span {
    display: none !important;
}

body .slick-track .product-item .info .nasa-sku,
body .products .product-warp-item .info .nasa-sku {
    color: #9fa6ae !important;
    font-weight: 500 !important;
}

body .nasa-logo-retina img {
    max-width: 200px;
}


body .nasa-header-flex .nasa-flex-item-1-2 {
    width: 100%;
    padding: 0px 20px;
}

body .nasa-header-flex .nasa-flex-item-1-1,
body .nasa-header-flex .nasa-flex-item-1-3 {
    width: max-content !important;
}

body .nasa-flex-item-1-2-0 {
    width: max-content !important;
}

body .nasa-actived-filter {
    background-color: #ffff !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.03);
    border-radius: 10px !important;
    border: 0px !important;
}

body .nasa-product-details-page .large-12.nasa-content-panel,
body .nasa-product-details-page .nasa-content-description .nasa-content-panel,
body .nasa-product-details-page .product_meta {
    background-color: #ffff !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.03);
    padding: 20px 10px;
    border-radius: 10px !important;
    border: none !important;
}

body .nasa-wrap-item-thumb {
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.03);
    border-radius: 10px !important;
    border: 1px solid rgb(246, 246, 246);
}

body .nasa-add-to-cart-fixed {
    z-index: 100 !important;
}

body .nasa-product-details-page .product-info .nasa-wrap-flex.info-modern-1 {
    flex-direction: column !important;
    border-radius: 6px !important;
}

body .nasa-product-details-page .large-7.product-gallery {
    max-width: 500px !important;
}

body .best-category a.nasa-a-tab {
    color: white !important;
}

body .nasa-product-details-page .large-5 {
    min-width: calc(100% - 520px) !important;
    min-height: 405px !important;
    width: calc(100% - 520px) !important;
    padding: 20px !important;
    margin-left: 20px;
    border-radius: 10px !important;
    border: 5px solid #ffff !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.03);
    background: #ffff !important;
}

body .nasa-product-details-page .product-info .nasa-wrap-flex.info-modern-1 .nasa-col-flex {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    background: #ffff !important;
    box-shadow: unset !important;
    border-radius: 0px !important;
}

body .nasa-product-details-page .cart .nasa-buy-now {
    background: #06D6A0 !important;
    color: #fff !important;
}

body .nasa-product-details-page .cart .nasa-buy-now:hover {
    background: #F3F6FD !important;
    color: #000 !important;
}

body .ur-frontend-form.ur-frontend-form--bordered form button[type="submit"] {
    color: #ffff !important;
}

body .product-info .nasa-wrap-flex.info-modern-1 .nasa-col-flex:last-child {
    background: #ffff !important;
}


body .product-images-slider.images-popups-gallery,
body .nasa-product-details-page .nasa-item-main-image-wrap {
    border-radius: 10px !important;
    overflow: hidden !important;
    max-height: 550px !important;
    width: 100% !important;
    min-width: 100% !important;
    height: 100% !important;
    min-height: 440px !important;
}

body .product-images-slider.images-popups-gallery {
    background-color: #ffff;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.03);
    margin-left: 10px;
}

body .nasa-single-product-2-columns .main-images>div,
body .nasa-main-image-default-wrap,
body .nasa-main-image-default-wrap div {
    width: 100% !important;
    min-width: 100% !important;
    height: 100% !important;
}


body .nasa-product-details-page .product-info .nasa-col-flex bdi {
    font-size: 35px !important;
    color: #000;
}

body .related-product .product-type-simple .main-img img {
    mix-blend-mode: unset !important;
}

body .product .product-info-wrap.info {
    padding: 0px 5px !important;
}


body .category-page .slick-track .product,
body .product-page .slick-track .product {
    min-height: 400px !important;
    background: #ffff !important;
    border-radius: 6px;
}


body #masthead {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 500;
    width: 100%;
    padding: 0 !important;
    background-color: #ffff !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.03);
}


body .menu-sec ul li:first-child {
    margin-right: 15px !important;
}

body .product-item .info .nasa-sku {
    line-height: 1.3;
    font-size: 90%;
}

body .info-before-header {
    width: 100%;
    background-image: repeating-linear-gradient(45deg, rgba(118, 118, 118, 0.05) 0px, rgba(118, 118, 118, 0.05) 19px, rgba(59, 59, 59, 0) 19px, rgba(59, 59, 59, 0) 67px, rgba(195, 195, 195, 0) 67px, rgba(195, 195, 195, 0) 87px, rgba(121, 121, 121, 0) 87px, rgba(121, 121, 121, 0) 133px, rgba(250, 250, 250, 0) 133px, rgba(250, 250, 250, 0) 172px, rgba(106, 106, 106, 0.05) 172px, rgba(106, 106, 106, 0.05) 197px, rgba(151, 151, 151, 0.05) 197px, rgba(151, 151, 151, 0.05) 226px, rgba(219, 219, 219, 0) 226px, rgba(219, 219, 219, 0) 260px), repeating-linear-gradient(45deg, rgba(70, 70, 70, 0) 0px, rgba(70, 70, 70, 0) 40px, rgba(220, 220, 220, 0) 40px, rgba(220, 220, 220, 0) 79px, rgba(95, 95, 95, 0.05) 79px, rgba(95, 95, 95, 0.05) 103px, rgba(15, 15, 15, 0.05) 103px, rgba(15, 15, 15, 0.05) 148px, rgba(51, 51, 51, 0) 148px, rgba(51, 51, 51, 0) 186px, rgba(225, 225, 225, 0) 186px, rgba(225, 225, 225, 0) 202px, rgba(60, 60, 60, 0) 202px, rgba(60, 60, 60, 0) 239px, rgba(67, 67, 67, 0) 239px, rgba(67, 67, 67, 0) 259px), repeating-linear-gradient(45deg, rgba(146, 146, 146, 0) 0px, rgba(146, 146, 146, 0) 40px, rgba(166, 166, 166, 0) 40px, rgba(166, 166, 166, 0) 54px, rgba(156, 156, 156, 0) 54px, rgba(156, 156, 156, 0) 71px, rgba(134, 134, 134, 0) 71px, rgba(134, 134, 134, 0) 95px, rgba(77, 77, 77, 0) 95px, rgba(77, 77, 77, 0) 111px, rgba(26, 26, 26, 0) 111px, rgba(26, 26, 26, 0) 153px, rgba(46, 46, 46, 0) 153px, rgba(46, 46, 46, 0) 202px, rgba(197, 197, 197, 0) 202px, rgba(197, 197, 197, 0) 216px), linear-gradient(90deg, rgb(30, 178, 248), rgb(46, 36, 197));
    padding: 7px;
    text-align: center;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 0.5px solid #E9ECEF !important;
    overflow: hidden;
    position: relative;
    height: 37px;
    transition: transform 0.3s ease-in-out;
}

.text-slider {
    display: flex;
    position: relative;
    width: 100%;
    justify-content: center;
    align-items: center;
}

.text-slide {
    position: absolute;
    opacity: 0;
    transition: opacity 0.3s ease, transform 0.3s ease;
    color: #fbb614;
    font-weight: 700;
}


.text-slide.active {
    opacity: 1;
    /* Torna a frase visível */
    transform: translateX(0);
    /* Garante que a frase esteja centralizada */

}

.text-slide.next {
    transform: translateX(100%);
    /* Frase seguinte entra pela direita */
}

.text-slide.prev {
    transform: translateX(-100%);
    /* Frase anterior sai pela esquerda */
}

@keyframes slide {

    0%,
    33.33% {
        transform: translateX(0%);
    }

    33.34%,
    66.66% {
        transform: translateX(-100%);
    }

    66.67%,
    100% {
        transform: translateX(-200%);
    }
}

body .info-before-header a {
    text-decoration: none !important;
    font-weight: 600 !important;
    color: #fbb614 !important;
}



body .info-before-header .flex {
    min-width: 100px !important;
}

body .info-before-header p {
    font-size: 18px !important;
    font-weight: bold !important;
    font-style: italic !important;
}

body .info-before-header .elementor-container {
    color: #000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 auto;
}


body .info-before-header .flex-item p,
body .info-before-header .flex-item {
    color: #000;
    display: flex;
    align-items: center;
}


body #nasa-ajax-store {
    background: #F3F6FD !important;
}


body .filters-container,
body .product-warp-item .product {
    background: #ffff !important;
    border-radius: 6px;
    padding: 10px;
}

body .nasa-topbar-change-view-wrap {
    min-width: 100px !important;
}

/*  .nasa-tip  */



body .filters-container {
    margin: 20px 0px !important;
}

body .product-warp-item .product .main-img {
    background: #ffff !important;
}

body .products .product-warp-item .product {
    min-height: 400px !important;
}

body .info-before-header .flex-item p {
    margin-left: 20px;
    margin-bottom: 0px !important;
}

body .info-before-header .flex-item p i {
    margin-right: 5px;
    color: #0074fe;
}

body .m125 {
    margin-bottom: 93px;
}

body .header-icons svg {
    color: #000 !important;
}


body button.nasa-buy-now,
body .menu-secundario li a {
    color: #000 !important;
}

.small-12.entry-summary {
    top: 150px;
}

body .customer-info.customer-info-addr {
    display: flex;
    flex-direction: column !important;
}

body .customer-info.customer-info-addr select {
    margin-top: 5px;
}

body .product-page .nasa-accessories-total-price-bg,
body .order-steps,
body #mobile-navigation .root-item.li_accordion.active,
body .nasa-header-search-wrap .nasa-show-search-form {
    background: #F3F6FD !important;
    border: none !important;
}


body #mobile-navigation .root-item.li_accordion .sub-menu {
    padding-left: 40px !important;
}

body #mobile-navigation .root-item.li_accordion .sub-menu .sub-menu {
    padding-left: 10px !important;
}


body .nasa-single-product-slide .nasa-item-main-image-wrap {
    background: #ffff !important;
}

body .cart_totals {
    border: 2px solid #F3F6FD !important;
    border-radius: 10px !important;
}


body .product-img .back-img img,
body .product-img .main-img img {
    max-height: 300px !important;
    object-fit: contain;
}


body .product-type-simple {
    position: relative;
}

body .nasa-single-share .ns-social {
    display: none !important;

}

body .product-type-simple .back-img,
body .product-type-simple .main-img {
    background: #fafbff !important;
    border-radius: 6px;
}

body .product-type-simple .alpus-product-brand-thumbnail {
    position: absolute;
    top: 10px;
    left: 10px !important;
    z-index: 5;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}



body .product-type-simple .alpus-product-brand-thumbnail img {
    height: 30px !important;
    max-height: 30px !important;
    min-height: 30px !important;
}


body .ns-sku-lbl,
body .nasa-sku {
    color: #0074fe !important;
}

body .product-type-simple .attachment-full {
    height: 50px !important;
}


body .product img.attachment-woocommerce_thumbnail {
    max-height: 215px !important;
}


body .product-type-simple bdi {
    color: grey;
}

body .product-type-simple .back-img img {
    border-radius: 6px;
    border: 1px solid #E9ECEF;
}

body .product-type-simple .main-img img {
    mix-blend-mode: multiply;
    border-radius: 6px;
}

body .elementor-element-7954d0a .product-type-simple .main-img {
    background: #FFFF !important;
    border-bottom: 1px solid #E9ECEF !important;
}


body .elementor-element-7954d0a .product-type-simple .main-img img,
body .elementor-element-7954d0a .product-type-simple .main-img {
    border-bottom-right-radius: 0px !important;
    border-bottom-left-radius: 0px !important;
}



body #nasa-breadcrumb-site {
    background: #F3F6FD;
    background-image: unset;
}

body .page-numbers li {
    border: 1px solid #E9ECEF;
    width: 36px;
    min-width: 36px;
    height: 36px;
    border-radius: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product .button {
    background-color: #ff6600;
    color: white;
    padding: 10px 20px;
    text-transform: uppercase;
    border-radius: 5px;
}

.product .button:hover {
    background-color: #e65c00;
}


body .nasa-top-row-filter .nasa-widget-toggle {
    border: 1px solid #E9ECEF;
    padding: 0px 15px;
    border-radius: 60px;
}



body .elementor-element-77fe5099 .elementor-container {
    max-width: 100% !important;
}

body .fixed-already .nasa-elements-wrap {
    padding: 0px !important;
}


body .item-search {
    padding-bottom: 5px;
}


body .product-category {
    margin-right: 10px !important;
}

body #nasa-wishlist-sidebar,
.small-12.entry-summary {
    top: 0px !important;
}


body .small-12.entry-summary.active {
    top: 150px !important;
}


body .product-category img {
    border-radius: 100% !important;
    border: 2px solid #EDF2FB;
    width: 100px !important;
    min-width: 100px !important;
    height: 100px !important;
    min-height: 100px !important;
    object-fit: contain !important;
    mix-blend-mode: multiply;
    background: #ffff !important;
}

body .item-search img {
    border-radius: 6px !important;
    mix-blend-mode: multiply;
    background: #F3F6FD !important;
    border: 1px solid #F3F6FD !important;
    min-width: 90px;
}

body .item-search .nasa-title-item {
    color: #000 !important;
    font-size: 14px !important;
}

body .item-search small {
    color: #0074fe !important;
    font-weight: 600 !important;
}

body .fixed-already .logo .header_logo {
    max-height: unset !important;
}


body .tt-menu {
    border: none !important;
    border-radius: 6px;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.1) !important;
}

body #nasa-login-register-form .nasa-form-content {
    margin: 0px !important;
    padding: 15px !important;
    padding-top: 0px !important;
}

body #gt_float_wrapper .gt_float_switcher .gt-selected img {
    width: 15px !important;
    height: 15px !important;
}

body #gt_float_wrapper .gt_float_switcher .gt-current-lang,
body #gt_float_wrapper .gt_float_switcher .gt-current-lang span {
    font-size: 14px !important;
}

body #gt_float_wrapper .gt-current-lang {
    padding: 0px 10px !important;
}

body .translate-area {
    min-width: 100px;
    background: #ffff;
    border-radius: 4px;
    position: relative !important;
    min-height: 30px;
    margin-left: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #DEE2E6;
}

body #gt_float_wrapper {
    display: block !important;
    position: relative;
    z-index: 500 !important;
    border: 1px solid #DEE2E6 !important;
    border-radius: 6px;
    background: white;
}

body #gt_float_wrapper .gt_options {
    display: block !important;
    position: absolute;
    right: -8px;
    top: 30px;
    width: 100%;
    z-index: 1 !important;
    min-width: 180px;
    border-radius: 6px;
    background: #ffff;
    min-height: 120px;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.3) !important;
}

body #gt_float_wrapper .gt_options a {
    display: flex;
    font-size: 14px;
    align-items: center;
}


body #gt_float_wrapper .gt_options.hide {
    height: 0px !important;
    overflow: hidden !important;
    min-height: 0px !important;
}


body #gt_float_wrapper .gt_options.hide a {
    display: none !important;
}


body #gt_float_wrapper .gt_options a img {
    width: 20px;
    height: 20px;
}

body .underline {
    text-decoration: underline !important;
    text-decoration-color: #ffff;
}


#gt_float_wrapper {
    top: 4px !important;
    padding: 0px !important;
    right: 15px !important;
}

#gt_float_wrapper .gt_float_switcher {
    height: 23px !important;
    box-shadow: unset !important;
}

body #gt_float_wrapper {
    display: block !important;
}


body #gt_float_wrapper div,
body #gt_float_wrapper div,
body #gt_float_wrapper .gt-selected {
    box-shadow: none !important;
}

body nav ul .menu-item-38255 a {
    padding: 3px 10px;
    color: #ffff !important;
    background: #06D6A0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 60px;
}


body .config-box {
    padding: 5px 10px;
    color: #ffff !important;
    background: #06D6A0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 60px;
}

body .nasa-login-register-warper .nasa-active {
    min-height: 600px;
    transition: all 0.3s ease-in-out;
}

body .nasa-login-register-warper {
    height: auto !important;
    width: 350px !important;
    border: none !important;
    border-radius: 6px !important;
}

body #nasa-menu-sidebar-content {
    top: 0px !important;
}

body .elementor-element-578fe0b3 {}

body .elementor-element-578fe0b3 .slick-slide {
    height: 80px !important;
    margin: 0px 5px !important;
}

body .elementor-element-578fe0b3 .slick-slide img {
    height: 80px !important;
    filter: grayscale(9);
}


body .sr7-bullets .sr7-bullet {
    border: 2px solid #0074fe !important;
    opacity: 1 !important;
    background: #ffff !important;
}

body .sr7-bullets .sr7-bullet.selected {
    background: #0074fe !important;
}

body .custom.sr7-arrows {
    background: rgba(0, 0, 0, 0.8) !important;
}

body .woocommerce-products-header {
    display: none !important;
}

body .woocommerce-Price-amount {
    padding-top: 5px;
}

body .woocommerce-products-header img {
    max-width: 200px;
    margin: 0 auto;
}

body .slick-list .product-item.grid {
    padding: 0px !important;
    margin-left: 10px !important;
    margin-right: 10px !important;
}


body .user-registration.ur-frontend-form {
    margin-top: 80px !important;
    border: none !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.1) !important;
    border-radius: 6px !important;
}

body .woocommerce-form.woocommerce-form-track-order input,
body .checkout-modern-left-wrap input,
body .checkout-modern-left-wrap .select2-selection--single,
body .user-registration.ur-frontend-form select,
body .user-registration.ur-frontend-form input {
    border: none !important;
    border-radius: 6px !important;
    background: #F3F6FD !important;
}

body .payment_box.payment_method_cod {
    background: #F3F6FD !important;
}

body .nasa-title-my-account-page,
body .nasa-heading-title {
    font-size: 22px !important;
    font-weight: bold !important;
}


body .login-registration .ur-form-row h5,
body .user-registration.ur-frontend-form h3 {
    font-size: 18px !important;
    font-weight: bold !important;
}


body .login-registration .ur-form-row h5 {
    text-transform: none !important;
}

body .login-registration p {
    max-width: 900px !important;
    margin: 10px auto !important;
}


body .user-registration.ur-frontend-form .btn {
    border-radius: 4px !important;
    border: none !important;
    text-transform: capitalize !important;
}

body .user-registration.ur-frontend-form .btn:hover {
    background: #0074fe !important;
}

body .nasa-login-register-warper input {
    border: none !important;
}

body .nasa-form-logo-log {
    display: flex !important;
    justify-content: space-between !important;
}


body .ur-frontend-form.login {
    border: none !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.1) !important;
    border-radius: 6px !important;
}

body .login-registration .ur-form-row {
    flex-direction: column !important;
    align-items: center;
    justify-content: center !important;
}


body .login-registration .ur-form-row .ur-form-grid {
    width: 100% !important;
    max-width: 700px;
}

.nasa-title-my-account-page {
    color: #ffff;
}

body .login-registration .ur-form-row .ur-form-title {
    display: none !important;
}

body .ur-frontend-form.login-registration .user-registration input.input-text {
    border: none !important;
    border-radius: 6px !important;
    background: #F3F6FD !important;
}

body .boxskuptext {
    display: flex;
    flex-direction: column;
    font-size: 18px !important;
    color: #000;
    margin: 15px 0px !important;
}


body .boxskuptext .txt {
    margin-top: 10px;
    font-size: 14px;
    font-weight: 400 !important;
}

body .boxskuptext strong {
    font-size: 18px !important;
    font-weight: bold !important;
    color: #0074fe !important;
}

body .account-nav-wrap .account-user {
    border: none !important;
    background: #0074fe !important;
    color: #ffff !important;
    border-radius: 6px !important;
    margin-bottom: 20px !important;
}

body .account-nav-wrap .account-user .wc-user {
    color: #F3F6FD !important;
}

body .account-nav-wrap .account-user .user-name {
    font-size: 20px !important;
    color: #ffff !important;
}


body .woocommerce-MyAccount-navigation li {
    margin: 20px 0px !important;
    border: none !important;
}

body .woocommerce-MyAccount-content input,
body .woocommerce-MyAccount-content select {
    background: #F3F6FD !important;
    border: none !important;
}


body .woocommerce-MyAccount-content fieldset {
    border-color: #DEE2E6 !important;
}

body .woocommerce-MyAccount-navigation li.is-active {
    color: #0074fe !important;
    background: #F3F6FD !important;
    padding: 10px 0px !important;
    padding-right: 10px !important;
}

body .woocommerce-MyAccount-navigation li.is-active svg,
body .woocommerce-MyAccount-navigation li.is-active a {
    color: #0074fe !important;
}


body .woocommerce-MyAccount-navigation li.is-active i {
    margin-top: 5px !important;
}

body .woocommerce-MyAccount-navigation a {
    border: none !important;
    background: none !important;
}


body .account-nav-wrap.vertical-tabs {}

body .woocommerce-MyAccount-content .wcmtx-my-account-links a {
    border: 1px solid #DEE2E6 !important;
    border-radius: 6px !important;
    box-shadow: none !important;
}

body .woocommerce-MyAccount-navigation-link svg {
    color: #6C757D !important;
}

body .whatsappwidget {
    position: fixed;
    bottom: 40px;
    right: 40px !important;
    z-index: 10 !important;
}



body .pop {
    bottom: 40px;
    margin: 100px;
    color: black;
    background: green;
    width: 250px;
    height: 250px;
}

body .whatsappwidget .toggle-chat {
    background: #25D366 !important;
    width: 70px;
    height: 70px;
    display: flex;
    font-size: 40px;
    color: #ffff;
    border-radius: 100%;
    align-items: center !important;
    text-align: center;
    justify-content: center !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.1);
    border: 3px solid #ffff !important;
}




body .whatsappwidget .toggle-chat .icon {
    margin-left: 3px;
    margin-bottom: 3px;
}


body .whatsappwidget .users-list {
    position: absolute;
    top: -460px;
    right: 0px;
    width: 350px;
    height: 450px;
    background: #ffff;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
    padding: 0px;
    margin: 0px !important;
    overflow: hidden;
    display: none;
}


body .whatsappwidget .users-list.active {
    display: block !important;
}

body .whatsappwidget .users-list .header-chat {
    width: 100%;
    height: 100px;
    background: #095b53 !important;
    display: flex;
    padding: 10px 20px;
    color: #ffff;
}


body .whatsappwidget .users-list .header-chat .icon {
    font-size: 30px;
    margin-right: 15px;
    margin-top: 7px;
}

body .whatsappwidget .users-list .header-chat h5 {
    margin: 3px !important;
    font-weight: bolder;
    font-size: 18px;
}


body .whatsappwidget .users-list .header-chat .txt div {
    font-size: 13px;
}


body .whatsappwidget .users-list .header-chat strong {
    font-weight: bold;
}

body .whatsappwidget .users-list li {
    display: flex;
    width: 100%;
    padding: 10px 10px;
    border-radius: 6px;
    margin-top: 15px;
    cursor: pointer;
    background: #F3F6FD !important;
}

body .whatsappwidget .tab-items {
    display: flex;
    align-items: center;
    width: 100%;
    border-bottom: 1px solid #E9ECEF;
    margin-bottom: 10px;
}

body .whatsappwidget .tab-items .tab {
    padding: 10px 0px;
    padding-left: 10px;
    position: relative;
    margin-right: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer !important;
}


body .whatsappwidget .tab-items .tab.active {
    color: #25D366 !important;
}

body .whatsappwidget .tab-items .tab.active::after {
    position: absolute;
    left: 0px;
    bottom: -5px;
    width: 100%;
    min-width: 60px;
    height: 4px;
    background: #25D366;
    content: "";
}

body .whatsappwidget .list {
    padding: 5px 20px;
    overflow-y: auto;
    max-height: 300px;
    background: #ffff;
    padding-bottom: 20px;
    display: none;
}


body .whatsappwidget .users-list .list.active {
    display: block !important;
}

body .whatsappwidget .users-list .list {
    padding: 5px 20px;
    overflow-y: auto;
    max-height: 280px;
    background: #ffff;
    padding-bottom: 20px;
}

body .whatsappwidget .users-list a:hover {
    color: #000;
}


body .whatsappwidget .users-list li:hover {
    color: #000 !important;
    background: #E1E5F2 !important;
}


/* width */
body .whatsappwidget .users-list .list::-webkit-scrollbar {
    width: 5px;
}

/* Track */
body .whatsappwidget .users-list .list::-webkit-scrollbar-track {
    background: #f1f1f1;
}

/* Handle */
body .whatsappwidget .users-list .list::-webkit-scrollbar-thumb {
    background: #E6E6E9;
}

body .whatsappwidget .users-list .list small {
    font-weight: 600;
    margin-bottom: 10px;
    color: #6C757D;
}

body .whatsappwidget .users-list li h5 {
    margin: 0px !important;
    font-size: 14px;
    font-weight: bolder !important;
}


body .whatsappwidget .users-list li span {
    font-size: 13px;
    font-weight: 600;
}

body .whatsappwidget .users-list li .avatar {
    width: 48px;
    height: 48px;
    min-width: 48px;
    margin-right: 10px;
    background: #ffff !important;
    border-radius: 100%;
}


body .whatsappwidget .users-list li .avatar img {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 100%;
}


body .whatsappwidget .users-list li .text div {
    font-size: 12px;
}

body #nasa-breadcrumb-site {
    background-position: center;
    background-repeat: no-repeat;
    background-size: contain;
}

@media (min-width: 1920px) {
    body {
        font-size: 1.2rem;
    }

    .container {
        max-width: 80%;
    }
}



@media screen and (max-width:900px) {


    body .info-before-header,
    body #featured-products .slick-arrow,
    body #gt_float_wrapper {
        display: none !important;
    }

    body .config-container,
    body .config-container.active {
        display: none !important
    }


    body .product-page .product-images-slider.images-popups-gallery {
        min-height: auto !important;
    }

    body #featured-products .slick-track {
        flex-direction: column !important;
        min-width: 100% !important;
        width: 100% !important;
    }

    body #news-products .nasa-tabs,
    body .featured-products .nasa-tabs {
        display: flex;
        flex-direction: column !important;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    body .whatsappwidget .users-list {
        max-width: 300px !important;
    }

    body .product-page .nasa-single-product-scroll .nasa-product-info-wrap {
        padding: 0px !important;
    }

    body .product-page .nasa-single-product-scroll .nasa-product-info-wrap .product_title {
        padding: 0px !important;
        padding-top: 40px !important;
    }

    body .product-page .nasa-single-product-scroll .nasa-product-info-wrap .location-box-det {
        min-width: 100% !important;
        width: 100% !important;
    }

    body .product-page .nasa-single-product-scroll .nasa-product-info-wrap .products-arrow {
        position: absolute;
        top: 0px;
        right: 15px;
    }


    body #news-products .slick-arrow {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        margin-right: 15px;
    }

    body .product-page .product-images-slider.images-popups-gallery {
        margin-top: 20px;
    }

    body #main-content {
        margin-top: -20px;
    }


    body #best-category .elementor-button-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    body #news-products .nasa-tabs li,
    body .featured-products .nasa-tabs li {
        margin: 10px 0px !important;
    }

    body #news-products .nasa-dft.nasa-title,
    body #featured-products .nasa-dft.nasa-title {
        margin-bottom: 30px !important;
    }

    body .featured-products .nasa-wrap-column.slick-slide {
        min-width: 100% !important;
        width: 100% !important;
    }


    body .wcmtx-my-account-links {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
        min-width: 100%;
    }

    body #best-category .main-img {
        border: 2px solid #fff !important;
    }

    body .products.large-block-grid-4 {
        margin: 0px !important;
        padding: 0px !important;
        justify-content: space-around !important;
    }

    body .products.large-block-grid-4 .product-warp-item {
        width: calc(50% - 15px) !important;
        margin: 8px 0px !important;
        padding: 0px !important;
    }


    body .product .product-info-wrap.info {
        padding: 5px !important;
    }


    .products .nasa-group-btns.nasa-btns-product-item .btn-wishlist {
        opacity: 0;
        right: 20px !important;
        z-index: 1000 !important;
        position: absolute !important;
        top: 50px !important;
        background: #ffff !important;
        transition: all 0.5s ease-in-out !important;
    }


    body .products .product:hover .nasa-group-btns.nasa-btns-product-item .btn-wishlist {
        opacity: 1 !important;
    }


    body #best-category .product-info-wrap.info {
        margin-top: 100px !important;
    }

    body .nasa-product-grid .add-to-cart-grid {
        width: 40px !important;
        height: 40px !important;
        display: flex !important;
        background: #fff !important;
        align-items: center !important;
    }


    body .product .nasa-icon.cart-icon.nasa-flex {
        background: #0084ff !important;
        color: #ffff !important;
    }



    body .product .product-info-wrap.info div {
        justify-content: unset !important;
    }

    body .wcmtx-my-account-links .wcmamtx_dashboard_link {
        width: 47% !important;
    }


    body .whatsappwidget .toggle-chat {
        width: 50px !important;
        height: 50px !important;
        min-height: 50px;
        max-height: 50px;
        display: flex;
        max-width: 50px !important;
        min-width: 50px !important;
        padding: 0px !important;
        font-size: 25px !important;
    }

    body .whatsappwidget .toggle-chat .icon {
        margin: 0px !important;
    }

    body .whatsappwidget {
        bottom: 70px;
        right: 20px !important;
    }

    body #nasa-breadcrumb-site {
        margin-top: -24px !important;
    }


    body .rma-form .flex {
        flex-direction: column !important;
    }

    body .rma-form .input,
    body .rma-form .block {
        width: 100% !important;
        max-width: 100% !important;
    }

    body .rma-form .block {
        margin-top: 20px !important;
    }


    body .rma-form .post-button {
        margin: 20px auto !important;
    }


    body .products-arrow {
        right: 10px !important;
    }

    body .nasa-product-info-wrap {
        padding: 20px !important;
        border-radius: 6px;
    }

    body .nasa-product-details-page .large-5 {
        width: 100% !important;
        min-width: 100% !important;
    }

    body .nasa-product-details-page .focus-info {
        flex-direction: column !important;
        padding: 0px 15px !important;
    }

    body .main-images .nasa-item-main-image-wrap,
    body .nasa-single-product-scroll .product-gallery {
        padding: 0px !important;
    }

    body .nasa-product-details-page #nasa-tab-description {
        padding: 0px 15px !important;
        border: 0px solid red !important;
    }

    body .nasa-single-product-scroll .product-gallery {
        padding: 0px 5px !important;
    }

    body .nasa-product-details-page .large-12.columns .nasa-tab-wrap {
        padding: 0px 5px !important;
    }

    body .nasa-product-details-page .large-12.columns .nasa-tab-wrap ul {
        background: #ffff;
        box-shadow: 0px 3px 10px rgba(114, 114, 114, 0.1);
        border-radius: 10px;
        padding: 20px 5px !important;
    }

    body .footer-zone footer {
        padding: 0px 15px !important;
    }

    body .footer-zone .footer-items {
        padding: 0px !important;
    }

    body .product-images-slider.images-popups-gallery,
    body .nasa-product-details-page .large-5 {
        margin-left: 0px !important;
    }

}

body .product-images-slider.images-popups-gallery,
body .nasa-product-details-page .large-5 {
    min-height: 550px !important;
}



body .nasa-crazy-load .category-page.nasa-with-sidebar-classic .main-img img {
    mix-blend-mode: unset !important;
}

body .nasa-mobile-nav-wrap .menu-item-heading.ns-menu-heading {
    display: none !important;
}


body li.default-menu .toggle-accordion .sp1 {
    display: none;
}

body li.default-menu.active .toggle-accordion .sp1 {
    display: none;
}

body .nasa-menu-accordion .removeBtn {
    background: transparent !important;
    font-weight: 300 !important;
    width: 30px !important;
    height: 30px !important;
    min-width: 30px !important;
    max-width: 30px !important;
    border-radius: 100%;
    position: absolute;
    color: #4A4E69 !important;
    top: 3px !important;
    right: 15px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center !important;
    cursor: pointer;
    border: none !important;
    padding: 0px !important;
    font-size: 20px;
}

body .nasa-menu-accordion .removeBtn.bm {
    top: 8px !important;
}

body .nasa-menu-accordion a.accordion {
    display: none !important;
}

body .black-window {
    z-index: 550 !important;
}

li.default-menu .nav-dropdown-mobile {
    display: none;
}

body li.default-menu.active .nav-dropdown-mobile.show {
    display: block !important;
}

body .flex-space {
    width: 100% !important;
    display: flex;
    align-items: center;
}

body .flex-div {
    display: flex;
    align-items: center;
}

body .flex-div img {
    width: 25px;
    height: 25px;
}

body #cart-sidebar {
    top: 0px !important;
}

body .flex-space strong {
    text-decoration: underline !important;
}

body .product .btn-wishlist {
    position: static !important;
    display: flex;
    align-items: center;
    background: #ffff;
}

body .price.nasa-single-product-price del bdi {
    font-size: 16px !important;
}

body .price.nasa-single-product-price ins bdi {
    color: #0084ff !important;
}

body .advanced-woo-labels.awl-label-type-single {
    color: #0084ff !important;
    z-index: 300 !important;
}

body .product .product-img-wrap {
    min-height: 200px;
    height: 200px !important;
}

body .product .product-img-wrap img {
    min-height: 190px;
    height: 100% !important;
}


body .wcapf-filter-item {
    margin-bottom: 10px !important;
}


.product .btn-wishlist {
    display: none;
}

body .product .nasa-group-btns.nasa-btns-product-item .btn-wishlist {
    display: flex !important;
}


body .slick-list .product .main-img {
    background: #ffff;
}




body mark {
    background: transparent !important;
}

body .slick-list .product .main-img img {
    height: 100% !important;
    width: 100% !important;
    background: #ffff;
    max-height: 210px !important;
}


body .nasa-single-product-tab.active {
    border: 2px solid #0074fe !important;
    border-radius: 6px !important;
}

body .col-sidebar button {
    background: transparent !important;
}


body .flex-location {
    margin: 4px 0px;
    margin-right: 6px;
    display: flex;
    align-items: center;
    font-size: 10px;
}

body .flex-location {
    font-size: 11px !important;
}

body .flex-location .dot {
    width: 7px;
    height: 7px;
    border-radius: 100%;
    margin-right: 5px;
}


body .product-page .flex-location {
    font-size: 15px !important;
}

body .product-page .price.nasa-single-product-price {
    margin: 0px !important;
}

body .product-page .btn-wishlist .nasa-tip-content {
    top: 25px !important;
}


body .wcapf-active-filter-item,
body .wcapf-active-filter-items {
    border: none !important;
    color: #ffff !important;
}

body .wcapf-reset-filters-btn-wrapper .wcapf-reset-filters-btn {
    background: transparent !important;
}

body a.nasa-show-one-line {
    overflow: unset !important;
    white-space: unset !important;
    display: none;
}

body .product .product-info-wrap.info {
    padding-top: 20px !important;
    background: #ffff;
    min-height: 140px;
}

body .product-info-wrap.info .woocommerce-loop-product__title {
    display: -webkit-box !important;
    /* Para truncar texto */
    -webkit-line-clamp: 3 !important;
    /* Limita a 3 linhas */
    -webkit-box-orient: vertical !important;
    /* Configura orientação */
    overflow: hidden !important;
    /* Esconde texto extra */
    text-overflow: ellipsis !important;
    /* Adiciona "..." no fim, se necessário */
    min-height: 4.5em !important;
    /* Altura fixa para 3 linhas */
    line-height: 1.5em;
    /* Altura de cada linha */
    padding: 0 !important;
    /* Remove qualquer padding adicional */
    margin: 0 !important;
    /* Remove margens extras */
}

body .product .nasa-sku {
    width: 100%;
}

body #wrapper,
body #main-content {
    background: #ffff;
}

body .checkout-modern-right-wrap {
    border-radius: 6px !important;
    padding: 30px 20px !important;
    background: #ffff;
    margin-top: 20px;
    border: 1px solid #E9ECEF;
    height: max-content !important;
    min-height: max-content !important;
}

body .woocommerce-MyAccount-navigation-link.woocommerce-MyAccount-navigation-link--customer-logout,
body .nasa-remove-compare {
    color: red !important;
    background: #FFF0F3 !important;
    padding: 7px 20px;
    border-radius: 6px;
}



body .woocommerce-MyAccount-navigation-link.woocommerce-MyAccount-navigation-link--customer-logout svg,
body .MyAccount-navigation .woocommerce-MyAccount-navigation-link a svg {
    color: red !important;
}

body .nasa-static-sidebar .product-remove a {
    color: red !important;
    background: #FFF0F3 !important;
    border-radius: 100%;
    width: 30px;
    height: 30px;
    color: red !important;
    margin-left: 10px;
}


body .woocommerce-MyAccount-navigation-link.woocommerce-MyAccount-navigation-link--customer-logout a,
body .woocommerce-MyAccount-navigation-link.woocommerce-MyAccount-navigation-link--customer-logout i {
    color: red !important;
}


body .page-wrapper.nasa-view-compare .stock span {
    color: #06D6A0 !important;
}


body .download-link {
    display: flex;
    align-items: center;
    padding: 5px 15px;
    font-size: 16px;
    color: #000;
    width: max-content !important;
}

body .download-link img {
    width: 30px;
    margin-left: 10px;
    height: 30px;
}



body .download-no-link {
    padding: 6px 20px;
    background: #FFC2D1;
    width: max-content;
    border-radius: 6px;
    color: red;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

body .nasa-downloads-panel {
    border: 5px solid red !important;
    max-width: 1400px !important;
    display: flex;
    flex-wrap: wrap;
}

body .nasa-slide-style li.nasa-slide-tab {
    border: none !important;
}


body .nasa-single-product-tab a {
    font-weight: 500 !important;
}


body .product-page .nasa-panels {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

body .product-page .nasa-panels .nasa-panel {
    width: 100%;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

body .product .quick-view.btn-link.quick-view-icon {
    display: none !important;
}

body .edit-box {
    border: 1px solid #E9ECEF;
    padding: 20px;
    width: 100%;
    border-radius: 6px;
    position: relative;
}


body .edit-box h5 {
    margin: 10px 0px !important;
    font-size: 14px !important;
}


body .edit-box h5 span {
    color: grey;
}

body .edit-box h3 {
    text-transform: none;
    font-size: 130%;
    border-bottom: none;
    margin-bottom: 0;
}

body .text-red {
    color: red !important;
}

body .edit-box .edit-button-float {
    position: absolute;
    bottom: 10px;
    right: 10px;
    height: 40px;
    border-radius: 4px;
    padding: 10px 15px;
    font-size: 18px !important;
    background: #0074fe;
    color: #ffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

body .nasa-subitem-acc.nasa-hello-acc {
    color: #000 !important;
}

body .edit-box .edit-button-float i {
    margin-left: 10px !important;
}

body .checkout-modern-left-wrap.show .edit-box {
    display: none !important;
}

body .checkout-modern-left-wrap .checkout-group {
    height: 0px !important;
    overflow: hidden !important;
    margin-top: -15px;
}

body .checkout-modern-left-wrap.show .checkout-group {
    display: block !important;
    height: auto !important;
    overflow: visible !important;
    margin-top: 0px !important;
}

body .woocommerce-form.woocommerce-form-track-order {
    max-width: 500px;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.07) !important;
    border-radius: 6px;
    background: #ffff !important;
    margin: 0px auto !important;
    padding: 20px !important;
}


body .woocommerce-customer-details {
    width: 100%;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.07) !important;
    border-radius: 6px;
    background: #ffff !important;
    margin: 0px auto !important;
    padding: 20px !important;
}

body .shop_table.my_account_orders tbody td.woocommerce-orders-table__cell-order-actions .woocommerce-button.button.view {
    color: #0074fe !important;
}


body .woocommerce-table--order-details tfoot {
    background: #F3F6FD !important;
}

body #mobile-navigation span.menu-image-title-after {
    text-decoration: none !important;
    font-weight: 400 !important;

}


body .nasa-add-to-cart-fixed {
    z-index: 1000 !important;
}


body .col-flex {
    display: flex;
    flex-direction: column;
    padding-right: 30px;
    border-right: 1px solid #DEE2E6;
    margin-right: 20px;
}


body .container-wrap.page-checkout .checkout-breadcrumb.rtl-text-righ {
    display: none !important;
}


body .products.list .product {
    min-height: max-content !important;
    height: max-content !important;
}


body .products.list .product {
    min-height: max-content !important;
    height: max-content !important;
}


body .products.list .product .btn-nasa-wishlist {
    background: unset !important;
}



body .product .price bdi {
    color: #0084ff !important;
    font-weight: 550;
}

body .product .price del bdi {
    color: grey !important;
}

body .nasa-list-stock-status {
    color: #06D6A0 !important;
}

body #mobile-navigation span,
body #mobile-navigation strong,
body #mobile-navigation a {
    text-transform: capitalize !important;
    font-weight: 450 !important;
    text-decoration: none !important;
}


body #best-category .product .product-info-wrap {
    padding-top: 50px !important;
}

body #best-category .product {
    min-height: 430px !important;
    border-radius: 6px;
    background: #ffff;
}

.woocommerce-MyAccount-content p {
    display: none;
}

body #best-category a.nasa-a-tab {
    color: #ffff !important;

    font-size: 26px;
}


body #best-category a.nasa-a-tab:after {
    background-color: #ffff !important;
    border-color: #ffff !important;
}


body .nasa-add-to-cart-fixed .boxskuptext {
    margin: 0px !important;
}

body .nasa-add-to-cart-fixed h3 .txt {
    display: none !important;
}


body .widget_price_filter .ui-slider .ui-slider-range {
    background: #0084ff !important;
    border: none !important;
}

body .widget_price_filter .price_slider {
    background: #fff !important;
    border-color: #F8F9FA !important;
    height: 5px !important;
}

body #featured-products .item-product-widget {
    border-radius: 6px;
    background: #ffff !important;
    min-height: 80px;
    display: flex;
    align-items: center;
}

body #featured-products .slick-track {
    display: flex !important;
    justify-content: space-between !important;
}

body #featured-products .slick-slide {
    max-width: 31% !important;
    margin: 20px 0px !important;
}

body #featured-products .slick-slide .woocommerce-Price-amount {
    color: #0084ff !important;
    font-weight: 600;
}


body #featured-products .slick-slide .sku-widget {
    color: #9fa6ae !important;
    font-weight: 500 !important;
    font-size: 13.05px;
}

body .product-images-slider.images-popups-gallery .item-wrap {
    display: none;
}


body .product-images-slider.images-popups-gallery .item-wrap:nth-child(1) {
    display: block !important;
}


body .product-page .product-info .cart .woocommerce-info {
    order: 2 !important;
    width: max-content !important;
    color: #155724;
    /* Dark green text (default) */
    background-color: #F0FFF1;
    /* Darker green for the background */
    border-color: #218838;
    /* Darker green border */
    font-size: 1rem;
    /* Adjust font size */
    padding: 10px 15px;
    /* Add more padding if needed */
    border-radius: 0.25rem;
    /* Rounded corners (optional) */
}

body .woocommerce-MyAccount-content p {
    display: block !important;
}
</style>

<style>
@supports (-webkit-appearance: none) or (-moz-appearance: none) {
    .nasa-sidebar-off-canvas input[type=checkbox] {
        --active: #0084ff;
        --active-inner: #fff;
        --focus: 2px rgba(39, 94, 254, .3);
        --border: #BBC1E1;
        --border-hover: #275EFE;
        --background: #fff;
        --disabled: #F6F8FF;
        --disabled-inner: #E1E6F9;
        -webkit-appearance: none;
        -moz-appearance: none;
        height: 21px;
        outline: none;
        display: inline-block;
        vertical-align: top;
        position: relative;
        margin: 0;
        cursor: pointer;
        border: 1px solid var(--bc, var(--border));
        background: var(--b, var(--background));
        transition: background 0.3s, border-color 0.3s, box-shadow 0.2s;
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:after {
        content: "";
        display: block;
        left: 0;
        top: 0;
        position: absolute;
        transition: transform var(--d-t, 0.3s) var(--d-t-e, ease), opacity var(--d-o, 0.2s);
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:checked {
        --b: var(--active);
        --bc: var(--active);
        --d-o: .3s;
        --d-t: .6s;
        --d-t-e: cubic-bezier(.2, .85, .32, 1.2);
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:disabled {
        --b: var(--disabled);
        cursor: not-allowed;
        opacity: 0.9;
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:disabled:checked {
        --b: var(--disabled-inner);
        --bc: var(--border);
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:disabled+label {
        cursor: not-allowed;
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:hover:not(:checked):not(:disabled) {
        --bc: var(--border-hover);
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:focus {
        box-shadow: 0 0 0 var(--focus);
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:not(.switch) {
        width: 21px;
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:not(.switch):after {
        opacity: var(--o, 0);
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:not(.switch):checked {
        --o: 1;
    }

    .nasa-sidebar-off-canvas input[type=checkbox]+label {
        display: inline-block;
        vertical-align: middle;
        cursor: pointer;
        margin-left: 4px;
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:not(.switch) {
        border-radius: 7px;
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:not(.switch):after {
        width: 5px;
        height: 9px;
        border: 2px solid var(--active-inner);
        border-top: 0;
        border-left: 0;
        left: 7px;
        top: 4px;
        transform: rotate(var(--r, 20deg));
    }

    .nasa-sidebar-off-canvas input[type=checkbox]:not(.switch):checked {
        --r: 43deg;
    }
}

.nasa-sidebar-off-canvas * {
    box-sizing: inherit;
}

.nasa-sidebar-off-canvas *:before,
.nasa-sidebar-off-canvas *:after {
    box-sizing: inherit;
}



div.slide-right p {
    -moz-animation: 10s slide-right;
    -webkit-animation: 10s slide-right;
    -o-animation: 10s slide-right;
    animation: 10s slide-right;
    margin: 0;
}

div.slide-left p {
    -moz-animation: 10s slide-left;
    -webkit-animation: 10s slide-left;
    -o-animation: 10s slide-left;
    animation: 10s slide-left;
    margin: 0;
}

@-webkit-keyframes slide-right {
    from {
        margin-left: 100%;
        width: 300%;
    }

    to {
        margin-left: 0%;
        width: 100%;
    }
}

@-moz-keyframes slide-right {
    from {
        margin-left: 100%;
        width: 300%;
    }

    to {
        margin-left: 0%;
        width: 100%;
    }
}

@-o-keyframes slide-right {
    from {
        margin-left: 100%;
        width: 300%;
    }

    to {
        margin-left: 0%;
        width: 100%;
    }
}

@keyframes slide-right {
    from {
        margin-left: 100%;
        width: 300%;
    }

    to {
        margin-left: 0%;
        width: 100%;
    }
}

@-webkit-keyframes slide-left {
    from {
        margin-left: 0%;
        width: 100%;
    }

    to {
        margin-left: -100%;
        width: 300%;
    }
}

@-moz-keyframes slide-left {
    from {
        margin-left: 0%;
        width: 100%;
    }

    to {
        margin-left: -100%;
        width: 300%;
    }
}

@-o-keyframes slide-left {
    from {
        margin-left: 0%;
        width: 100%;
    }

    to {
        margin-left: -100%;
        width: 300%;
    }
}

@keyframes slide-left {
    from {
        margin-left: 0%;
        width: 100%;
    }

    to {
        margin-left: -100%;
        width: 300%;
    }
}



body .header-icons li {
    z-index: 3000 !important;
    position: relative !important;

}

body a.remove.nasa-stclose::before,
body a.remove.nasa-stclose::after {
    content: unset !important;
    color: transparent !important;
}

body .woocommerce-cart-form.nasa-shopping-cart-form,
body .shop_table.shop_table_responsive {
    overflow-x: scroll !important;
    max-width: 98% !important;
    width: 98% !important;
}

body .nasa-compare-item .nasa-remove-compare,
body a.remove.nasa-stclose {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 14px !important;
    color: transparent !important;
    width: 30px !important;
    height: 30px !important;
    min-height: 30px !important;
    min-width: 30px !important;
    border-radius: 100%;
    background: #FFF0F3 !important;
    padding: 0px !important;
}

body .nasa-compare-item .nasa-remove-compare i,
body a.remove.nasa-stclose i {
    color: red !important;
    font-size: 14px !important;
}

body a.remove.nasa-stclose:hover i,
body a.remove.nasa-stclose:hover {
    color: #ffff !important;
    background: red !important;
}


body .nav-icons-area li {
    position: absolute;
    z-index: 3000;
}

body .nav-icons-area {
    color: #ffff !important;
    position: relative !important;
    z-index: 30034000 !important;
}


body .ns_carousel_pro_checkout .btn-wishlist.btn-nasa-wishlist {
    position: absolute !important;
    z-index: 1000 !important;
    background: #ffff !important;
    box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.3) !important;
    border-radius: 6px !important;
    top: 4px !important;
    right: 0px !important;
}


body .location-box-det {
    display: block;
    padding: 4px 15px;
    border-radius: 6px;
    margin-top: 10px;
    margin-right: 10px;
    background: #F3F6FD;
    min-width: 200px;
    width: max-content !important;
    font-size: 15px !important;
}



body .location-box-det .flex-location {
    font-size: 13px !important;
}

body .location-box-det.text-green {
    color: green !important;
}


body .location-box-det.text-red {
    color: #e5383b !important;
}

body .location-box-det.text-gold {
    color: #fca311 !important;
}


body .product-page .location-box-det strong {
    color: #000 !important;
}

body .product-page .product-details .product-stocks .flex-location {
    font-size: 11px !important;
}

body .product-page .product-details .nasa-sku span,
body .product-page .product-details .nasa-sku {
    color: #9fa6ae !important;
    font-weight: 500;
}

body .product-page .location-box-det .dot {
    width: 10px !important;
    height: 10px !important;
}

body .cart_list_product_quantity .woocommerce-Price-amount {
    padding: 0px !important;
}

body .prd-description,
body .list-locations-stock {
    display: none;
}


body .prd-description p {
    margin: 0px !important;
}


body .product-stocks {
    display: flex;
}

body .products.large-block-grid-4.list .product-stocks {
    display: none !important;
}


body .products.large-block-grid-4.list .prd-description,
body .products.large-block-grid-4.list .list-locations-stock {
    display: flex !important;
    flex-wrap: wrap !important;
}

body .products.large-block-grid-4.list .list-locations-stock strong {
    color: #000 !important;
}



body .products.large-block-grid-4.list .product-info-wrap.info .woocommerce-loop-product__title {
    min-height: unset !important;
    height: unset !important;
}



body .woocommerce-customer-details {
    padding-bottom: 40px !important;
}

body .product .name,
body .product-title {
    font-size: 14px !important;
}

body .product .name:hover,
body .product-title:hover {
    font-weight: 600 !important;
}

body #temporary_address_fields {
    margin-bottom: 20px !important;
}

body .hidechat {
    display: none !important;
}

body #billing_nif_field {
    margin-top: 80px !important;
    color: red !important;
    position: relative !important;
    bottom: 0px !important;
    left: 0px !important;
    height: 90px;
}

body #billing_nif_field label {
    margin-left: 0px !important;
    margin-top: 5px !important;
}

body #main-content form .form-row.nasa-actived.thwcfd-field-tel label {
    margin-left: 0px !important;
}
</style>

<style>
body .rma-form .flex {
    display: flex;
    width: 100%;
}


body .rma-form {
    margin: 0 auto;
    width: 100%;
    max-width: 1380px;
}

body .add-new-product {
    background: #08d6a0 !important;
}

body .rma-form .col-lg-4 {
    width: 33%;
    min-width: 33%;
}

body .rma-form .mb-4 {
    margin-bottom: 15px;
}

body .add-new-product {
    color: #ffff;
}

body .post-button {
    color: #ffff;
    padding: 15px 20px;
    border-radius: 4px;
    width: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
}


body .rma-form hr {
    margin: 30px 0px;
}


body .rma-form .flex {
    display: flex;
    width: 100%;
    justify-content: space-between;
}

body .rma-form .flex .block {
    width: 49%;
}


body .rma-form .block-product {
    position: relative;
}


body .rma-form .new-container {
    position: absolute;
    left: 0px;
    width: 100%;
    bottom: -90px;
    display: flex;
    align-items: center;
    justify-content: center;
}


body .rma-form .btn {
    cursor: pointer;
}

body .rma-form .new-container .btn {
    padding: 10px 15px;
    border-radius: 60px;
    display: flex;
    align-items: center;
}

body .rma-form .new-container .btn i {
    font-size: 30px;
    margin-right: 10px;
}

body .rma-form .main-dets,
body .rma-form .new-product-return {
    width: 100%;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid rgb(235, 235, 235) !important;
}


body .rma-form h5 {
    font-size: 20px;
    font-weight: 600;
    margin: 0px;
}


body .rma-form .flex-ln {
    display: flex;
    align-items: center;
}


body .rma-form .text-red {
    color: rgb(233, 68, 68);
}

body .rma-form .logo-box {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    margin-bottom: 30px;
}


body .rma-form .logo-box img {
    max-width: 300px;
}

body #element-to-print {
    max-width: 850px;
    padding: 30px;
    border: 1px solid silver;
    background-color: #ffff;
    font-family: "Montserrat", serif;
    font-optical-sizing: auto;
    font-style: normal;
    display: none;
}



body #element-to-print .product-box {
    padding: 0px;
    border: 1px solid rgb(201, 201, 201);
    font-family: "Montserrat", serif;
    font-optical-sizing: auto;
    font-style: normal;
    font-size: 13px;
    margin-bottom: 20px;
    border-bottom: none;
}


body #element-to-print .product-box .count {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #0074fe;
    font-size: 16px;
    color: #ffff;
    border-radius: 100%;
    margin-right: 10px;
}

body #element-to-print .product-box .d-flex {
    align-items: center;
}


body .rma-form .new-product-return .count {
    width: 40px;
    height: 40px;
    background: #0074fe;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: 20px;
    color: #ffff;
    font-weight: 600;
    border-radius: 100%;
    margin-right: 10px;
}

body .text-blue {
    color: #0074fe;
}

body .new-product-return .col-3 .block {
    width: 32%;
}


body .new-product-return .space {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}


body .new-product-return .space .remove-product {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    cursor: pointer;
    font-size: 20px;
    border-radius: 100%;
    color: rgb(234, 88, 88);
}

body .block label {
    margin-bottom: 10px;
}


body .print-paper {
    width: 100%;
    height: 350px;
    margin-bottom: 20px;
    border: 1px solid rgb(201, 201, 201);
    padding: 20px;
}

body .data-header {
    width: 100%;
    height: 82px;
    border: 1px solid rgb(201, 201, 201);
    margin-bottom: 30px;
    font-size: 14px;
    font-weight: 550;
}


body .data-header strong {
    font-weight: 650;
}

body .data-header .box3 {
    width: 100%;
    height: 40px;
    border-top: 1px solid rgb(201, 201, 201);
    display: flex;
    justify-content: space-between;
}

body .data-header .full {
    width: 33%;
    height: 40px;
    padding: 10px;
}

body .data-header .box3 .box {
    width: 33%;
    height: 100%;
    padding: 10px;
}

body .data-header .box3 .box.p {
    border-left: 1px solid rgb(201, 201, 201);
    border-right: 1px solid rgb(201, 201, 201);
}

body .header-doc {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    flex-direction: column;
    font-family: "Montserrat", serif !important;
    font-optical-sizing: auto;
}

body .header-doc .logo img {
    max-width: 350px;
    margin-bottom: 20px;
    height: 70px;
}

body .header-doc h3 {
    text-transform: uppercase;
    font-size: 20px;
    font-weight: bold;
    margin: 5px 0px;
    font-family: "Montserrat", serif !important;
    font-optical-sizing: auto;
}

body .rma-form .form-select,
body .rma-form .form-control {
    background: #F3F6FD !important;
    border: none !important;
    border-radius: 6px !important;

}


body .rma-form .post-button {
    background: #0074fe !important;
    font-weight: 600;
}


body form hr {
    background-color: silver !important;
    margin: 10px 0px;
}

body .rma-form .col-3 .block {
    width: 32%;
}

body .rma-form select,
body .rma-form input {
    width: 100%;
    height: 40px;
}

body .product-box .col-3 {
    border-left: 1px solid rgb(201, 201, 201);
    border-right: 1px solid rgb(201, 201, 201);
}

body .product-box .bx {
    padding: 15px 20px;
    border-bottom: 1px solid rgb(201, 201, 201);
}


body .footer-paper .column {
    font-size: 13px;
}



body .toggle-product small.text-primary {
    font-size: 14px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
}

@keyframes HandMove {
    0% {
        transform: rotate(-20deg);
    }

    50% {
        transform: rotate(20deg);
    }

    100% {
        transform: rotate(-20deg);
    }
}


body button#moveToTop,
body .config-box {
    color: #000 !important;
    font-weight: 600 !important;
    background: #fbb614 !important;
}


body .config-box i {
    animation: HandMove 1.5s infinite;
    transform-origin: center;
    font-size color: #000 !important;
}


.countdown-container {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.countdown-item {
    padding: 20px 15px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.countdown-item h1 {
    margin: 0px;
    font-size: 20px;
    font-weight: bolder;
    margin-bottom: 6px;
    width: 50px;
    color: #001b77;
    height: 50px;
    background: #ffff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.countdown-item span {
    font-size: 15px;
    color: #ffff;
}


body .countdown-banner-area {
    position: relative !important;
}

body .weirdo {
    width: 100%;
    height: 100px !important;
    background: red !important;
}

body .countdown-banner-area img {
    max-width: 1400px;
    margin: 0 auto !important;
}

body .countdown-banner-area {
    display: flex;
    align-items: center;
    justify-content: center;
    padding-top: 10px;
    padding-bottom: 20px;
}


body .wcmamtx_dashboard_link {
    background-color: transparent !important;
    border: none !important;
}

body .wcmamtx_notice_div.dashboard_text {
    padding: 4px !important;
    background-color: rgb(116, 159, 227) !important;
    border-radius: 10px !important;
}

body .woocommerce-MyAccount-navigation.nasa-MyAccount-navigation,
body .wcmamtx_notice_div.dashboard_text .wcmamtx_notice_div_uppertext,
body .product-info .compare {
    display: none !important;
}


body .countdown-banner-area .countdown-container {
    position: absolute;
    right: 260px;
    top: 70px;
}

.btn-share {
    margin-top: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    /* igual ao Favoritos e Comparar */
    height: 32px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
    color: #666;
    transition: all 0.3s ease;
}

.btn-share:hover {
    color: #007cba;
    /* azul WooCommerce no hover */
}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css"
    integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<script>
document.addEventListener('DOMContentLoaded', function() {
    const productImageBtns = document.querySelectorAll('.product-image-btn');

    productImageBtns.forEach(btnGroup => {
        // 👉 Cria o botão Compartilhar com classes do Elesi + personalizadas
        const shareBtn = document.createElement('a');
        shareBtn.href = "javascript:void(0);";
        shareBtn.className = "btn-share btn-link wishlist-icon nasa-tip nasa-tip-right nasa-tiped";
        shareBtn.rel = "nofollow";
        shareBtn.setAttribute('data-tip', 'Compartilhar'); // Tooltip certo aqui

        shareBtn.innerHTML = `
            <svg class="nasa-icon" width="20" height="20" viewBox="0 0 32 32" fill="currentColor">
                <path d="M26.129 2.139c-2.355 0-4.265 1.91-4.265 4.265 0 0.409 0.061 0.803 0.168 1.178l-12.469 5.226c-0.737-1.277-2.114-2.139-3.693-2.139-2.355 0-4.265 1.91-4.265 4.265s1.91 4.265 4.265 4.265c1.234 0 2.343-0.527 3.122-1.366l8.034 5.774c-0.314 0.594-0.494 1.27-0.494 1.988 0 2.356 1.91 4.266 4.265 4.266s4.265-1.91 4.265-4.266c0-2.355-1.91-4.264-4.265-4.264-1.253 0-2.376 0.544-3.157 1.404l-8.023-5.765c0.33-0.605 0.518-1.299 0.518-2.037 0-0.396-0.058-0.778-0.159-1.143l12.478-5.23c0.741 1.26 2.107 2.108 3.675 2.108 2.355 0 4.265-1.91 4.265-4.266 0-2.355-1.91-4.265-4.265-4.265z"></path>
            </svg>
        `;

        btnGroup.appendChild(shareBtn);

        // Evento click
        shareBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentURL = window.location.href;
            const productTitle = document.title;

            if (navigator.share) {
                navigator.share({
                    title: productTitle,
                    text: "Veja este produto:",
                    url: currentURL
                }).catch((err) => {
                    console.error('Erro ao compartilhar:', err);
                });
            } else {
                copyToClipboard(currentURL);
                alert("📋 Link do produto copiado para a área de transferência!");
            }
        });

        function copyToClipboard(text) {
            const textarea = document.createElement("textarea");
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand("copy");
            document.body.removeChild(textarea);
        }
    });
});



document.addEventListener('DOMContentLoaded', function() {
    const shareLabel = document.querySelector('.nasa-share-label');

    if (shareLabel) {
        // Torna o texto "Compartilhar" clicável
        shareLabel.style.cursor = "pointer";

        // Adiciona evento ao clicar
        shareLabel.addEventListener('click', function() {
            const currentURL = window.location.href;
            const productTitle = document.title;

            if (navigator.share) {
                // 📲 Mobile: Web Share API
                navigator.share({
                    title: productTitle,
                    text: "Veja este produto:",
                    url: currentURL
                }).catch((err) => {
                    console.error('Erro ao compartilhar:', err);
                });
            } else {
                // 🖥️ Desktop: Copiar o link
                copyToClipboard(currentURL);
                alert("📋 Link do produto copiado para a área de transferência!");
            }
        });

        // Função auxiliar para copiar texto
        function copyToClipboard(text) {
            const textarea = document.createElement("textarea");
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand("copy");
            document.body.removeChild(textarea);
        }
    }
});




function paintKitLoja() {
    document.querySelectorAll('a.name').forEach(title => {
        if (title.textContent.toLowerCase().includes('kit loja')) {
            const productCard = title.closest('.product-info-wrap');
            if (productCard) {
                const flexLocations = productCard.querySelectorAll('.dot');
                flexLocations.forEach(location => {
                    if (location.style.backgroundColor !== 'green') {
                        location.style.backgroundColor = 'green';
                    }
                });
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', paintKitLoja);
const productsWrapper = document.querySelector('.products, .shop-products, .woocommerce');
if (productsWrapper) {
    const observer = new MutationObserver(() => {
        paintKitLoja();
    });
    observer.observe(productsWrapper, {
        childList: true,
        subtree: true
    });
}

// Reaplica após AJAX WooCommerce (caso temas usem hooks JS)
jQuery(document.body).on('updated_wc_div', paintKitLoja);
jQuery(document.body).on('wc_fragments_refreshed', paintKitLoja);

document.addEventListener('DOMContentLoaded', function() {
    const accountDiv = document.querySelector('.account-nav.account-user.hide-for-small');

    if (accountDiv) {

        const link = document.createElement('a');
        link.href = 'https://store.exportech.com.pt/my-account/';
        link.style.textDecoration = 'none';
        link.style.color = 'inherit';


        accountDiv.parentNode.insertBefore(link, accountDiv);
        link.appendChild(accountDiv);
    } else {
        console.log('O div não foi encontrado no DOM.');
    }
});

function fillskukit() {
    const produtos = document.querySelectorAll(".woosb-name");
    const description = document.querySelector(".nasa-content-panel");
    if (!description) return;

    const Paragraphs = description.querySelectorAll("p");
    const lastParagraph = Paragraphs[Paragraphs.length - 1];
    const text = lastParagraph.textContent;
    const regex = /\d+x\s([^\n]+)/g;
    const skus = [];
    let match;
    while ((match = regex.exec(text)) !== null) {
        skus.push(match[1].trim());
    }

    produtos.forEach((produto, i) => {
        if (skus[i]) {



            const link = produto.querySelector("a");
            if (link) {
                const href = link.getAttribute("href");

                const a = document.createElement("a");
                a.href = href;
                a.textContent = skus[i];
                a.style.color = "#0074fe";
                a.style.fontWeight = "bold";
                a.style.display = "block";
                a.target = "_blank"
                produto.appendChild(a);
            }
        }
    });

}
window.addEventListener("DOMContentLoaded", fillskukit);

document.addEventListener("DOMContentLoaded", () => {
    let lastScrollTop = 0;
    const infoBeforeHeader = document.querySelector(".info-before-header");
    let scrolledOnce = false; // Controle para evitar recolher na primeira carga





    function FillEmptyProductNames() {
        let product_titles = document.querySelectorAll(
            "a.name.nasa-bold.woocommerce-loop-product__title.nasa-show-one-line.short");
        product_titles.forEach(title => {
            let linkname = title.getAttribute("title");
            let linktext = title.innerHTML

            function reduceText(text, maxLength) {
                if (text.length <= maxLength) return text;
                return text.slice(0, maxLength).trim() + '...';
            }

            setTimeout(() => {
                setInterval(() => {
                    if (linktext.split("").length < 3) {
                        title.innerHTML = reduceText(linkname, 35);
                    }
                }, 1000);
            }, 1000);
        });
    }
    FillEmptyProductNames();



    window.addEventListener("scroll", () => {
        const currentScroll = window.pageYOffset;
        if (currentScroll > 500) {
            infoBeforeHeader.style.transform = "translateY(-100%)";
        } else {
            infoBeforeHeader.style.transform = "translateY(0)";
        }
    });



    function CheckEmptyTitles() {
        let cont = document.querySelectorAll("#nasa-single-product-tabs");
        if (cont.length > 0) {
            let items = document.querySelectorAll(
                ".nasa-accessories-product a.woocommerce-loop-product__title");
            items.forEach(element => {
                let title = element.getAttribute("title");
                if (element.innerHTML.split("").length <= 5) {
                    element.innerHTML = title;
                }
            });
        }
    }
    CheckEmptyTitles();



    function FixProductDetailsPageThumbnail() {
        setTimeout(() => {
            let mainImage = document.querySelectorAll("a.woocommerce-main-image");
            if (mainImage.length > 0) {
                let thumbnails = document.querySelectorAll(
                    ".product-thumbnails.images-popups-gallery .nasa-wrap-item-thumb");
                let Main = mainImage[0]
                let Images = document.querySelectorAll(".main-images .item-wrap img");



                for (var i = 0; i < thumbnails.length; i++) {
                    let thumb = thumbnails[i];
                    let src = Images[i].getAttribute("src");
                    thumb.addEventListener("click", () => {
                        let show = true;
                        // Main.setAttribute("href ", src);
                        Main.setAttribute("data-o_href", src);
                        Main.setAttribute("data-full_href", src);

                        Main.href = src;
                        Main.querySelector("img").setAttribute("srcset", src);
                        Main.querySelector("img").setAttribute("src", src);
                        Main.querySelector("img").setAttribute("data-large_image", src);
                        Main.querySelector("img").setAttribute("data-src", src);


                        setInterval(() => {
                            let zooms = document.querySelectorAll(".easyzoom-flyout");
                            if (zooms.length > 0 && show === true) {
                                if ((zooms[0].querySelector("img").getAttribute(
                                        "src") === src) === false) zooms[0]
                                    .querySelector("img").setAttribute("src", src);
                                show = false;
                            }
                        }, 50);


                    });
                }
            }
        }, 1000);
    }
    FixProductDetailsPageThumbnail()


});


document.addEventListener("DOMContentLoaded", () => {
    const slides = document.querySelectorAll(".text-slide");
    let currentIndex = 0;

    function updateSlides() {
        slides.forEach((slide, index) => {
            slide.classList.remove("active", "next", "prev");
            if (index === currentIndex) {
                slide.classList.add("active");
            } else if (index === (currentIndex + 1) % slides.length) {
                slide.classList.add("next");
            } else if (index === (currentIndex - 1 + slides.length) % slides.length) {
                slide.classList.add("prev");
            }
        });
    }

    function nextSlide() {
        currentIndex = (currentIndex + 1) % slides.length;
        updateSlides();
    }

    updateSlides(); // Inicializa a exibição
    setInterval(nextSlide, 4000); // Troca a cada 4 segundos
});

document.addEventListener("DOMContentLoaded", function(event) {



    /***   test  */
    function WhatsappWidget() {
        let Boxes = document.querySelectorAll(".whatsappwidget");
        for (var i = 0; i < Boxes.length; i++) {
            let box = Boxes[i];
            let Toggle = box.querySelector(".toggle-chat");
            let Chat = box.querySelector(".users-list");

            Toggle.addEventListener("click", () => {
                Chat.classList.toggle("active");
            });
        }

        function onClickOutside(ele, cb) {
            document.addEventListener('click', event => {
                if (!document.querySelector(ele).contains(event.target)) cb();
            });
        };

        if (document.querySelectorAll(".whatsappwidget").length > 0) {
            let box = document.querySelector(".users-list");
            onClickOutside('.whatsappwidget', () => box.classList.remove("active"));
        }
    }
    WhatsappWidget();



    if (document.querySelectorAll(".checkout.woocommerce-checkout").length > 0) {
        setTimeout(() => {
            let Container = document.querySelector(".checkout.woocommerce-checkout");
            let FormElement = Container.querySelector(".checkout-group.woo-billing");
            let NoneEditableData = document.createElement("div");
            let fullname = FormElement.querySelector("#billing_first_name").value + " " + FormElement
                .querySelector("#billing_last_name").value;
            let country = document.querySelector("#select2-billing_country-container").innerText;
            let nif = FormElement.querySelector("#billing_nif").value;

            let postalcode = FormElement.querySelector("#billing_postcode").value;
            let company = FormElement.querySelector("#billing_company").value;
            let address = FormElement.querySelector("#billing_address_1").value;
            let phone = FormElement.querySelector("#billing_phone").value;

            let state = document.querySelector("#select2-billing_state-container").innerText;
            let email = Container.querySelector("#billing_email").value;


            let newHtmlBox = `
        <div class="edit-box">
            <h3>Detalhes de facturação</h3>
            <h5>Nome: <span class='${fullname.split("").length <= 2 ? "text-red" : ""}'>  ${fullname.split("").length <= 2 ? "Preencha este campo para poder avançar com o envio" : fullname} </span> </h5>
            <h5>Nome da empresa : <span class='${company.split("").length <= 2 ? "text-red" : ""}'> ${company.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : company}</span></h5>
            <h5>Distrito / Município : <span class='${state.split("").length <= 2 ? "text-red" : ""}'> ${state.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : state}</span></h5>
            <h5>Localidade : <span class='${address.split("").length <= 2 ? "text-red" : ""}'> ${address.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : address}</span></h5>
            <h5>Telefone :  <span class='${phone.split("").length <= 2 ? "text-red" : ""}'>  ${phone.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : phone}</span></h5>
            <h5>Email:   <span class='${email.split("").length <= 2 ? "text-red" : ""}'>  ${email.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : email}</span></h5>
            <h5>Nº de contribuinte:  <span class='${nif.split("").length <= 2 ? "text-red" : ""}'>  ${nif.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : nif}</span></h5>
            <h5>País / Região : <span class='${country.split("").length <= 2 ? "text-red" : ""}'>  ${country.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : country}</span></h5> 
            <h5>Código postal : <span class='${postalcode.split("").length <= 2 ? "text-red" : ""}'> ${postalcode.split("").length <= 2 ? "Preencha este campo para poder avançar  com o envio" : postalcode}</span></h5>

            <div class="edit-button-float"> 
                 Editar dados de usúario
                <i class="fa-regular fa-pen-to-square"></i>
            </div>
        </div>`;


            let formErrors = [];
            if (fullname.split("").length <= 2) formErrors.push("fullname not filled");
            if (company.split("").length <= 2) formErrors.push("company not filled");
            if (state.split("").length <= 2) formErrors.push("state not filled");
            if (address.split("").length <= 2) formErrors.push("address not filled");
            if (phone.split("").length <= 2) formErrors.push("phone not filled");
            if (email.split("").length <= 2) formErrors.push("email not filled");
            if (nif.split("").length <= 2) formErrors.push("nif not filled");
            if (country.split("").length <= 2) formErrors.push("country not filled");
            if (postalcode.split("").length <= 2) formErrors.push("postal code not filled");

            let box = document.querySelector(".checkout-modern-left-wrap");

            function triggerButtons() {
                let steps = document.querySelectorAll(".button.nasa-shipping-step.nasa-switch-step");
                for (var i = 0; i < steps.length; i++) {
                    steps[i].click();
                }
            }


            if (formErrors.length === 0) {
                triggerButtons();
            } else {
                NoneEditableData.innerHTML = newHtmlBox;
                Container.insertBefore(NoneEditableData, document.querySelector("#nasa-step_billing"));
            }

            function ShowAndHideForm() {
                if (document.querySelectorAll(".edit-button-float").length > 0) {
                    let btn = document.querySelector(".edit-button-float");
                    btn.addEventListener("click", () => {
                        box.classList.toggle("show");
                    });
                }
            }


            let btn1 = document.querySelectorAll(".checkout-modern-wrap .customer-info-change");
            for (let i = 0; i < btn1.length; i++) {
                const btn = btn1[i];
                btn.addEventListener("click", (e) => {
                    e.preventDefault();
                    box.classList.add("show");
                });
            }

            ShowAndHideForm();
        }, 10);
    }



    setInterval(() => {
        let prices = document.querySelectorAll(".prstr");
        for (var i = 0; i < prices.length; i++) {
            let txt = prices[i].innerHTML;
            prices[i].innerHTML = txt.split(".")[0]
            if (prices[i].innerHTML.trim().split("").length <= 0) prices[i].innerHTML = 0;
        }
    }, 100);


    // mudar link do modal para redirecionar a pagina de registo de users.
    let Registerlink = document.querySelectorAll(".nasa-switch-register");
    for (let i = 0; i < Registerlink.length; i++) {
        Registerlink[i].removeAttribute("rel");
        Registerlink[i].setAttribute("href", "/registo");
    }


    let ti = document.querySelectorAll(".nasa-title-my-account-page");
    for (let i = 0; i < ti.length; i++) {
        ti[i].innerText = "Entrar";
        ti[i].style.color = "#000";
    }

    function DescriptionPageTextResume() {
        let descriptionText = document.querySelectorAll("#nasa-tab-description p");
        let text = document.createElement("div");
        for (var a = 0; a < descriptionText.length; a++) {
            text.innerText += descriptionText[a].innerHTML + ", ";
        }

        if (text.innerText.split("").length > 5) {

            let P = document.createElement("p");
            P.classList.add("txt");
            var truncated = text.innerText.split("").length > 245 ? text.innerText.substring(0, 245) + "..." :
                text.innerText;

            P.innerHTML = truncated;
            document.querySelector(".boxskuptext").appendChild(P);

            let brs = P.querySelectorAll("br");
            for (var i = 0; i < brs.length; i++) {
                brs[i].remove();
            }

        }

    }

    // dd trash icon to sidebar of products


    setInterval(() => {
        let lang = document.querySelectorAll(".gt_options");
        for (var i = 0; i < lang.length; i++) {
            if (lang[i].style.display === "none") {
                lang[i].classList.add("hide");
            } else {
                lang[i].classList.remove("hide");
            }
        }
    }, 100);


    setInterval(() => {
        let buttons = document.querySelectorAll("a.remove.nasa-stclose");
        for (var i = 0; i < buttons.length; i++) {
            if (buttons[i].querySelectorAll("i").length <= 0) {
                buttons[i].innerHTML = '<i class="fa-regular fa-trash-can"></i>';
            }
        }

        let buttons2 = document.querySelectorAll(".nasa-compare-item .nasa-remove-compare");
        for (var i = 0; i < buttons2.length; i++) {
            if (buttons2[i].querySelectorAll("i").length <= 0) {
                buttons2[i].innerHTML = '<i class="fa-regular fa-trash-can"></i>';
            }
        }

    }, 100);


    setTimeout(() => {
        let DropPlaces = document.querySelectorAll(".product_title.entry-title");
        for (let i = 0; i < DropPlaces.length; i++) {
            const element = DropPlaces[i];
            let newText = document.createElement("div");
            newText.classList.add("boxskuptext")
            newText.innerText = "";
            let Strong = document.createElement("strong");
            Strong.classList.add("cr-sku")
            Strong.innerHTML = document.querySelector(".sku").innerHTML;
            newText.appendChild(Strong);
            element.appendChild(newText);
            DescriptionPageTextResume();
        }
    }, 100);


    setTimeout(() => {
        let descriptions = document.querySelectorAll(".products .prd-description");
        for (var i = 0; i < descriptions.length; i++) {
            let descriptionTxt = descriptions[i].querySelectorAll("p");

            let text = document.createElement("div");
            for (var a = 0; a < descriptionTxt.length; a++) {
                text.innerText += descriptionTxt[a].innerHTML + ", ";
            }


        }
    }, 5000);


    // whatsapp widget tabs 
    let Tabs = document.querySelectorAll(".tab-container");
    for (let i = 0; i < Tabs.length; i++) {
        const btns = Tabs[i].querySelectorAll(".tab");
        const container = Tabs[i].querySelectorAll(".list");
        for (let a = 0; a < btns.length; a++) {
            const btn = btns[a];
            btn.addEventListener("click", () => {
                Tabs[i].querySelector(".tab.active").classList.remove("active");
                Tabs[i].querySelector(".list.active").classList.remove("active");

                btn.classList.add("active");
                container[a].classList.add("active");
            });
        }
    }


    function MobileMenuRedirect() {
        const MobileNavigations = document.querySelector("#mobile-navigation");
        let mainLinks = MobileNavigations.querySelectorAll("li.default-menu");
        let Lists = MobileNavigations.querySelectorAll("li.default-menu .nav-dropdown-mobile");

        if (MobileNavigations.querySelectorAll("li.default-menu.active").length > 0) {
            let uls = MobileNavigations.querySelectorAll("li.default-menu.active .nav-dropdown-mobile");
            uls[0].classList.toggle("show");
        }

        mainLinks.forEach(Link => {
            let SubLinks = Link.querySelectorAll("li.menu-item");
            let CurrentUrl = null;
            let CurrentSpan = null;
            const MainMenu = Link.querySelector("li.menu-item a.nasa-title-menu");
            if (Link.querySelectorAll("li.menu-item a.nasa-title-menu span.menu-image-title-after")
                .length > 0) {
                CurrentSpan = Link.querySelector(
                    "li.menu-item a.nasa-title-menu span.menu-image-title-after");
                CurrentUrl = MainMenu.getAttribute("href")
                Link.querySelector("li.menu-item a.nasa-title-menu").setAttribute("href",
                    'javascript:void(0);');
            }

            let FlexDiv = document.createElement("div");
            FlexDiv.classList.add("flex-space");
            let flexText = document.createElement("div");
            flexText.classList.add("flex-div");
            let buttonRemove = document.createElement("div");
            buttonRemove.classList.add("removeBtn");
            buttonRemove.classList.add("bm");
            buttonRemove.innerHTML = "+";
            let menuImg = MainMenu.querySelector("img");

            CurrentSpan.style.minWidth = "80px";
            CurrentSpan.style.textDecoration = "underline";
            let Toggle = true;

            flexText.appendChild(menuImg);
            flexText.appendChild(CurrentSpan);

            // MainMenu.style.backgroundColor = "green";
            FlexDiv.appendChild(flexText);
            FlexDiv.appendChild(buttonRemove);
            MainMenu.innerHTML = "";
            MainMenu.appendChild(FlexDiv);

            // CurrentSpan.style.backgroundColor = "green";
            // CurrentSpan.style.color = "gold";


            CurrentSpan.addEventListener("click", (e) => {
                window.location.href = CurrentUrl;
                Toggle = false;
            });


            function RunToggle() {
                if (Toggle === true) {
                    let Actives = MobileNavigations.querySelectorAll("li.active");
                    let RemoveButtons = MobileNavigations.querySelectorAll("li .removeBtn");
                    for (var i = 0; i < Lists.length; i++) Lists[i].style.display = "none";
                    for (var i = 0; i < Actives.length; i++) Actives[i].classList.remove("active");
                    //for(var i = 0; i < RemoveButtons.length; i++) RemoveButtons[i].innerHTML = "+"; 

                    Link.classList.toggle("active");
                    Link.querySelectorAll(".nav-dropdown-mobile")[0].classList.toggle("show");


                    buttonRemove.innerHTML = "-";
                    if (!Link.querySelectorAll(".nav-dropdown-mobile")[0].classList.contains("show"))
                        buttonRemove.innerHTML = "+";
                }
            }

            let SubMenus = Link.querySelectorAll(".nav-dropdown-mobile")[0].querySelectorAll(
                ".sub-menu");

            Link.addEventListener("click", (e) => {
                RunToggle();
            });


            Link.querySelector("a.accordion").addEventListener("click", (e) => {
                e.preventDefault();
                return true;
            });

            for (var i = 0; i < SubMenus.length; i++) {
                let menus = SubMenus[i].querySelectorAll("li.menu-item-has-children");
                for (var a = 0; a < menus.length; a++) {
                    if (menus[a].querySelectorAll("a.accordion").length > 0) {
                        // menus[a].style.backgroundColor = "gold"; 
                        let toggle = menus[a].querySelector(".nasa-title-menu");
                        let CrUrl = toggle.getAttribute("href")
                        toggle.setAttribute("href", 'javascript:void(0);');
                        //  toggle.style.backgroundColor = "gold"; 
                        let Container = menus[a].querySelector(".nav-column-links.nav-dropdown-mobile");

                        let Ttext = "";
                        let Tsvg = toggle.querySelector("svg");
                        toggle.querySelector("svg").remove();

                        let FlexDiv = document.createElement("div");
                        FlexDiv.classList.add("flex-space");
                        let textDiv = document.createElement("span");
                        let buttonRemove = document.createElement("div");
                        buttonRemove.classList.add("removeBtn");
                        textDiv.innerText = toggle.innerHTML;
                        buttonRemove.innerHTML = "+";

                        FlexDiv.appendChild(buttonRemove);
                        FlexDiv.appendChild(textDiv);
                        toggle.innerHTML = "";
                        toggle.appendChild(FlexDiv);


                        let GoToLink = false;
                        textDiv.addEventListener("click", (e) => {
                            window.location.href = CrUrl;
                            Toggle = false;
                            GoToLink = true;
                        });


                        function RunSubToggle() {
                            if (GoToLink === false) {
                                setTimeout(() => {
                                    Link.classList.add("active");
                                    for (var x = 0; x < Lists.length; x++) Lists[x].style
                                        .display = "none";
                                    Link.querySelectorAll(".nav-dropdown-mobile")[0].classList
                                        .add("show");

                                    buttonRemove.innerHTML = "-";

                                    Container.classList.toggle("show");
                                    if (!Container.classList.contains("show")) buttonRemove
                                        .innerHTML = "+";
                                    GoToLink = false;
                                }, 10);
                            }
                        }

                        toggle.addEventListener("click", () => {
                            RunSubToggle();
                        });

                    }
                }
            }
        });
    }

    let ShowMenu = false;
    setInterval(() => {
        if (document.querySelectorAll("li.default-menu .nav-dropdown-mobile").length > 0) {
            if (ShowMenu === false) {
                MobileMenuRedirect();
                ShowMenu = true;
            }
        }
    }, 100);


    setTimeout(() => {
        let bx = document.querySelector("#gt_float_wrapper");
    }, 10);


    setInterval(() => {
        let products = document.querySelectorAll(".products .product");
        for (var i = 0; i < products.length; i++) {
            if (products[i].querySelectorAll(".btn-wishlist").length >= 1) {

                if (products[i].querySelectorAll(".nasa-group-btns").length > 0) {
                    let wrapBtn = products[i].querySelector(".nasa-group-btns");
                    if (wrapBtn.querySelectorAll(".btn-wishlist").length === 0) {
                        let WishButton = products[i].querySelector(".btn-wishlist");
                        WishButton.style.backgroundColor = "red";
                        wrapBtn.appendChild(WishButton);
                    }
                }
            }
        }
    }, 10);



    const WallpaperByCategories = [{
            name: "cabos",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__cabos.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__cabos-mobile.png", // Mobile
            }
        },
        {
            name: "cctv",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__cctv.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__cctv-mobile.png", // Mobile
            }
        },
        {
            name: "acessoseassiduidade",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__acessos.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__acessos-mobile.png", // Mobile
            }
        },
        {
            name: "networking",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__networking.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__networking-mobile.png", // Mobile
            }
        },
        {
            name: "FibraÓptica",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__fibra.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__fibra-mobile.png", // Mobile
            }
        },
        {
            name: "sistemasincêndio",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__incendio.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__incendio-mobile.png", // Mobile
            }
        },
        {
            name: "vídeoporteiros",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__porteiros.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__porteiros-mobile.png", // Mobile
            }
        },
        {
            name: "sistemasdetecçãogases",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__gases.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__gases-mobile.png", // Mobile
            }
        },
        {
            name: "sistemasintrusão",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__intrusao.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__intrusao-mobile.png", // Mobile
            }
        },
        {
            name: "easanti-furto",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__EAS.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__EAS-mobile.png", // Mobile
            }
        },
        {
            name: "torniquetes",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__Torniquetes.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__Torniquetes-mobile.png", // Mobile
            }
        },
        {
            name: "detecçãodemetais",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__metais.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__metais-mobile.png", // Mobile
            }
        },
        {
            name: "sistemasdeemergência",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__emergencia.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__emergencia-mobile.png", // Mobile
            }
        },
        {
            name: "solucoesdeparqueamento",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__parque.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__parque-mobile.png", // Mobile
            }
        },
        {
            name: "acessórios",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__acessorios.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__acessorios-mobile.png", // Mobile
            }
        },
        {
            name: "atex",
            images: {
                img1: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__atex.png", // Desktop
                img2: "https://store.exportech.com.pt/wp-content/uploads/2023/10/cat__atex-mobile.png", // Mobile
            }
        },
    ];




    function RunAddAbanner() {

        if (document.querySelectorAll(".nasa-first-breadcrumb").length > 0 || document.querySelectorAll(
                ".nasa-product-details-page").length > 0) {
            let RackTextEl = null;
            let p = false;
            const CategoriesToBeAdd = ["cctv", "dome", "dissimuladas", "gravadores", "panorâmicas",
                "speeddomesptz", "tubular"
            ];
            const CategoriesToBeAdd2 = ["centrais", "sistemasincêndio", "convencional", "endereçável",
                "deteçãolineardeinundação", "fontesdealimentaçao", "comunicadores",
                "convencional", "endereçável", "detecçãolineardeinundação", "fontesdealimentação", "kits",
                "detectoreslinearesechamas", "Detectores Lineares e Chamas",
                "detectoreslineareschamas", "aspiração", "software", "supressãodeincêndio", "spraydetestes",
                "varadetestes", "comunicadoresreunimos"
            ]




            if (document.querySelectorAll(".nasa-product-details-page").length <= 0) {
                RackTextEl = document.querySelector(".nasa-first-breadcrumb").innerText.split(" ").join("")
                    .toLowerCase();
            } else {
                let a = document.querySelectorAll(".breadcrumb a")
                a.forEach(element => {
                    let text = element.innerText;
                    let Rtext = text.toLowerCase().split(" ").join("").trim();

                    if (Rtext === "sistemasincêndio" || Rtext === "bastidores" || CategoriesToBeAdd
                        .includes(Rtext) || CategoriesToBeAdd2.includes(Rtext)) {
                        RackTextEl = text.toLowerCase().split(" ").join("").trim()
                    }
                });
                p = true;
            }







            if (document.querySelectorAll(".config-banner").length > 0) return;


            if (RackTextEl === "bastidores") {

                if (!document.querySelector(".nasa-product-details-page")) {
                    ParentEl = document.querySelector(".large-12.columns.ns-before-archive-products")
                } else {
                    console.log("wosbs")
                    ParentEl = document.querySelector(".nasa-product-details-page");
                }

                let referenceEl;
                if (!document.querySelector(".nasa-product-details-page")) {
                    referenceEl = ParentEl.querySelector(".row.filters-container.nasa-filter-wrap")
                } else {
                    console.log("do you staff")
                    referenceEl = document.querySelector(".nasa-single-product-scroll")
                };



                const Bannerarea = document.createElement("div");
                Bannerarea.classList.add("countdown-banner-area");

                const ImageEl = document.createElement("img");
                const LinkTag = document.createElement("a");
                ImageEl.classList.add("config-banner");
                ImageEl.setAttribute("src", "https://i.ibb.co/Y4y3pQBL/970-x-90-Large-Leaderboard-2.png");
                LinkTag.setAttribute("href", "/configurador");
                LinkTag.append(ImageEl);
                const countDown = document.createElement("div");

                LinkTag.appendChild(ImageEl);
                Bannerarea.appendChild(countDown);
                Bannerarea.appendChild(LinkTag);

                ParentEl.insertBefore(Bannerarea, referenceEl);

                //  ParentEl.insertBefore(LinkTag, referenceEl);
            } else if (CategoriesToBeAdd2.includes(RackTextEl)) {
                const ImageLink = "https://i.imgur.com/a5SqlnF.png";



                setTimeout(() => {
                    console.clear();
                    console.log("GOOO = ", RackTextEl);
                    console.log("text = ", document.querySelector(".nasa-single-product-scroll"));
                }, 5000);


                let ParentEl;

                if (!document.querySelector(".nasa-product-details-page")) {
                    ParentEl = document.querySelector(".large-12.columns.ns-before-archive-products")
                } else {
                    console.log("wosbs")
                    ParentEl = document.querySelector(".nasa-product-details-page");
                }

                let referenceEl;
                if (!document.querySelector(".nasa-product-details-page")) {
                    referenceEl = ParentEl.querySelector(".row.filters-container.nasa-filter-wrap")
                } else {
                    console.log("do you staff")
                    referenceEl = document.querySelector(".nasa-single-product-scroll")
                };







                const Bannerarea = document.createElement("div");
                Bannerarea.classList.add("countdown-banner-area");

                const ImageEl = document.createElement("img");
                const LinkTag = document.createElement("a");
                ImageEl.classList.add("config-banner");
                ImageEl.setAttribute("src", ImageLink);
                LinkTag.setAttribute("href", "/product-category/sistemas-incendio/kits-si/");
                ImageEl.style.borderRadius = "8px";

                const countDown = document.createElement("div");

                LinkTag.appendChild(ImageEl);
                Bannerarea.appendChild(countDown);
                Bannerarea.appendChild(LinkTag);

                ParentEl.insertBefore(Bannerarea, referenceEl);

            } else if (CategoriesToBeAdd.includes(RackTextEl)) {
                const ImageLink = "https://i.imgur.com/GpwdUkP.png";







                let ParentEl;
                if (!document.querySelector(".nasa-product-details-page")) {
                    ParentEl = document.querySelector(".large-12.columns.ns-before-archive-products")
                } else {
                    console.log("wosbs")
                    ParentEl = document.querySelector(".nasa-product-details-page");
                }

                let referenceEl;
                if (!document.querySelector(".nasa-product-details-page")) {
                    referenceEl = ParentEl.querySelector(".row.filters-container.nasa-filter-wrap")
                } else {
                    console.log("do you staff")
                    referenceEl = document.querySelector(".nasa-single-product-scroll")
                };



                const Bannerarea = document.createElement("div");
                Bannerarea.classList.add("countdown-banner-area");

                const ImageEl = document.createElement("img");
                const LinkTag = document.createElement("a");
                ImageEl.classList.add("config-banner");
                ImageEl.setAttribute("src", ImageLink);
                LinkTag.setAttribute("href", "/product-category/cctv/cctv-hdcvi/");
                ImageEl.style.borderRadius = "8px";
                LinkTag.append(ImageEl);
                const countDown = document.createElement("div");

                LinkTag.appendChild(ImageEl);
                Bannerarea.appendChild(countDown);
                Bannerarea.appendChild(LinkTag);

                ParentEl.insertBefore(Bannerarea, referenceEl);




            }
        }
    }

    RunAddAbanner();

    setInterval(() => {
        RunAddAbanner();
    }, 2000);





    function Run(element) {
        let Cat = null;
        if (element.querySelectorAll(".breadcrumb-row .breadcrumb a").length > 1) {
            Cat = element.querySelectorAll(".breadcrumb-row .breadcrumb a")[1];
        } else {
            Cat = element.querySelector(".breadcrumb-row .nasa-first-breadcrumb");
        }

        if (Cat !== null && Cat !== undefined) {
            let comp = Cat.innerText.split(" ").join("").toLowerCase();




            for (let ct = 0; ct < WallpaperByCategories.length; ct++) {
                const El = WallpaperByCategories[ct];
                if (El.name.toLowerCase() === comp) {

                    element.style.backgroundImage =
                        `url(${window.innerWidth > 900 ? El.images.img1 : El.images.img2})`;
                    element.style.backgroundColor = `#ffff`;
                    element.style.backgroundPosition = `center center`;
                    element.style.backgroundRepeat = `no-repeat`;
                    element.style.backgroundSize = `cover`;
                }
            }
        }
    }


    const FilterByCategories = [{
            name: "cabos",
            filtertype: "popularity"
        },

    ];



    function ChangeCategoriesByType(element) {
        let Cat = null
        if (element.querySelectorAll(".breadcrumb-row .nasa-first-breadcrumb").length >= 1) {
            Cat = element.querySelector(".breadcrumb-row .nasa-first-breadcrumb");
        }

        if (Cat !== null && Cat !== undefined) {
            for (let ct = 0; ct < FilterByCategories.length; ct++) {
                const El = FilterByCategories[ct];
                let comp = Cat.innerText.split(" ").join("").toLowerCase();
                if (El.name.toLowerCase() === comp) {
                    const type = El.filtertype;


                    let fake_selector = document.querySelector(".woocommerce-ordering .nasa-ordering");
                    let fake_selector_active_element = fake_selector.querySelector(".nasa-current-orderby");
                    let fake_selector_options = document.querySelectorAll(".sub-ordering");
                    let real_selector = document.querySelector(".woocommerce-ordering select");
                    let real_selector_options = real_selector.querySelectorAll("option");

                    setTimeout(() => {
                        for (let i = 0; i < real_selector_options.length; i++) {
                            real_selector_options[i].selected = false;
                            if (real_selector_options[i].value.toLowerCase() === type) {
                                real_selector_options[i].selected = true;
                                real_selector.value = real_selector_options[i].value;
                                let fake_selector_options = document.querySelectorAll(
                                    ".sub-ordering a");

                                for (let x = 0; x < fake_selector_options.length; x++) {
                                    const val = fake_selector_options[x].getAttribute("data-value");
                                    let CL = fake_selector_options[x];
                                    if (val.toLocaleLowerCase() === type) {
                                        CL.click();
                                    }
                                }
                            }
                        }
                    }, 1);
                }
            }
        }
        setTimeout(() => {
            ChangeImageOfPage()
        }, 6000);
    }




    function ChangeImageOfPage() {
        const BreadCrumbContainer = document.querySelectorAll("#nasa-breadcrumb-site");
        for (let i = 0; i < BreadCrumbContainer.length; i++) {
            Run(BreadCrumbContainer[i]);
        }
    }


    const BreadCrumbContainer = document.querySelectorAll("#nasa-breadcrumb-site");
    for (let i = 0; i < BreadCrumbContainer.length; i++) {
        Run(BreadCrumbContainer[i]);
        ChangeCategoriesByType(BreadCrumbContainer[i]);
        Run(BreadCrumbContainer[i]);
    }


});


function TitleProductResume() {
    let products = document.querySelectorAll(".product a.name");
    for (let i = 0; i < products.length; i++) {
        if (!products[i].classList.contains("short")) {
            let text = products[i].innerText;
            products[i].innerText = text.split("").length > 50 ? text.substring(0, 50) + "..." : text;
            products[i].style.display = "block";
            products[i].classList.add("short");
        }
    }
}



// Configura o MutationObserver
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.type === 'childList') {
            TitleProductResume(); // Executa sempre que novos elementos forem adicionados ao DOM
        }
    });
});

// Observa a tabela de produtos (ajuste o seletor para o contêiner correto)
const targetNode = document.querySelector(".nasa-grid-wrap"); // Use o contêiner principal dos produtos
if (targetNode) {
    observer.observe(targetNode, {
        childList: true, // Observa adições e remoções de elementos
        subtree: true, // Observa mudanças em todos os níveis filhos
    });
}

// Executa a função inicialmente
setTimeout(() => {
    if (document.querySelectorAll("#best-category").length === 0) {
        setTimeout(() => {
            TitleProductResume();
        }, 100);
    }
}, 100);




setTimeout(() => {
    if (document.querySelectorAll(".product-page #nasa-single-product-tabs").length > 0) {

        function showTabDownloads() {
            let tabs = document.querySelector(".product-page .nasa-tabs-content .nasa-tabs");
            let tabsPanels = document.querySelector(".product-page .nasa-tabs-content .nasa-panels");
            let TabsItems = tabs.querySelectorAll(".product-page .nasa-single-product-tab");

            if (TabsItems.length >= 2) {
                TabsItems[1].classList.remove("last");

                let newTab = document.createElement("li");
                newTab.classList.add("nasa-single-product-tab", "download-tab", "last");

                let contentTab = document.createElement("a");
                contentTab.innerText = `Downloads`;
                newTab.appendChild(contentTab);
                tabs.appendChild(newTab);

                let newPanel = document.createElement("div");
                newPanel.classList.add("nasa-panel");

                let contentPanel = document.createElement("div");
                contentPanel.classList.add("large-12", "columns", "nasa-content-panel", "nasa-downloads-panel");

                newPanel.append(contentPanel);
                tabsPanels.appendChild(newPanel);
            }
        }

        function toggletabs() {
            let Panels = document.querySelectorAll(".product-page .nasa-tabs-content .nasa-panels .nasa-panel");
            let TabsItems = document.querySelectorAll(
                ".product-page .nasa-tabs-content .nasa-single-product-tab");

            for (let i = 0; i < TabsItems.length; i++) {
                let el = TabsItems[i];
                let panel = Panels[i];

                el.addEventListener("click", () => {
                    Panels.forEach(element => element.classList.remove("active"));
                    TabsItems.forEach(element => element.classList.remove("active"));

                    el.classList.add("active");
                    panel.classList.add("active");
                });
            }
        }

        function renderDownloads(sku, fichaUrl) {
            showTabDownloads();
            const downloadsArea = document.querySelector(".nasa-downloads-panel");
            if (!downloadsArea) return;

            const skuLower = sku.toLowerCase();

            // Se SKU contiver SDCS2, usa link fixo
            if (skuLower.includes("sdcs2")) {
                fichaUrl = "https://store.exportech.com.pt/wp-content/uploads/2023/10/SDCS2.pdf";
            }

            // Ficha Técnica
            const fichaLink = document.createElement("a");
            fichaLink.classList.add("download-link");
            fichaLink.setAttribute("href", fichaUrl);
            fichaLink.setAttribute("target", "_blank");
            fichaLink.innerText = "Ficha Técnica";

            const fichaImg = document.createElement("img");
            fichaImg.setAttribute("src", "https://store.exportech.com.pt/wp-content/uploads/2023/11/pdff.jpg");
            fichaLink.appendChild(fichaImg);

            downloadsArea.appendChild(fichaLink);

            // Certificado (apenas se SKU tiver lih ou je-h)
            let certificadoUrl = null;

            if (skuLower.includes("je-h")) {
                certificadoUrl =
                    "https://store.exportech.com.pt/wp-content/uploads/2023/10/DECLARATION%20OF%20CONFORMITY%20&%20PRODUCT%20CERTIFICATE%20-%20JE-H(St)H%20Bd%20FE180%20PH120%20EN.pdf";
            } else if (skuLower.includes("lih")) {
                certificadoUrl =
                    "https://store.exportech.com.pt/wp-content/uploads/2023/10/DECLARATION%20OF%20CONFORMITY%20&%20PRODUCT%20CERTIFICATE%20-%20LIH(St)H%20FE180%20PH120%20EN.pdf";
            }

            if (certificadoUrl) {
                const certLink = document.createElement("a");
                certLink.classList.add("download-link");
                certLink.setAttribute("href", certificadoUrl);
                certLink.setAttribute("target", "_blank");
                certLink.innerText = "Certificado";

                const certImg = document.createElement("img");
                certImg.setAttribute("src",
                    "https://store.exportech.com.pt/wp-content/uploads/2023/11/pdff.jpg");
                certLink.appendChild(certImg);

                downloadsArea.appendChild(certLink);
            }

            toggletabs();
        }

        let SkuCode = document.querySelectorAll(".cr-sku");
        if (SkuCode.length > 0) {
            const skuValue = SkuCode[0].innerHTML.trim();
            let cleanSku = skuValue;

            // Se houver "/", tenta primeiro a parte antes da "/"
            const skuParts = cleanSku.split("/");
            const skuFirst = skuParts[0];

            function fileExists(url) {
                return fetch(url, {
                        method: 'HEAD',
                        mode: 'cors',
                        cache: 'no-cache'
                    })
                    .then(response => response.ok)
                    .catch(() => false);
            }

            function tryRender(url) {
                fileExists(url).then(exists => {
                    if (exists) {
                        renderDownloads(skuValue, url);
                    }
                });
            }

            setTimeout(() => {
                const baseUrl = "https://store.exportech.com.pt/wp-content/uploads/2023/10/";

                if (skuParts.length > 1) {
                    // Tenta com a primeira parte (antes da "/")
                    const firstUrl = `${baseUrl}${skuFirst}.pdf`;
                    fileExists(firstUrl).then(exists => {
                        if (exists) {
                            renderDownloads(skuValue, firstUrl);
                        } else {
                            // Se não existir, tenta com o SKU completo
                            const fullUrl = `${baseUrl}${cleanSku}.pdf`;
                            tryRender(fullUrl);
                        }
                    });
                } else {
                    // Se não tem "/", usa direto
                    const fullUrl = `${baseUrl}${cleanSku}.pdf`;
                    tryRender(fullUrl);
                }
            }, 100);
        }
    }
}, 1000);

/**** end ****/

document.addEventListener('DOMContentLoaded', function() {
    const productTitles = document.querySelectorAll('.woocommerce-loop-product__title');

    productTitles.forEach((title) => {
        const lineHeight = parseFloat(window.getComputedStyle(title).lineHeight);
        const maxLines = 3;
        const maxHeight = lineHeight * maxLines;

        if (title.offsetHeight < maxHeight) {

            const extraSpace = maxHeight - title.offsetHeight;
            title.style.paddingBottom = `${extraSpace}px`;
        }
    });
});
</script>

<div class="<?php echo esc_attr($header_classes); ?>">
    <div class="sticky-wrapper">

        <!--<a href="/rma">Ficha de Parceiros (RMA)</a> foi pedido para remover -->
        <div id="masthead" class="site-header">
            <div class="info-before-header">
                <div class="text-slider">
                    <div class=" flex text-slide"><a class="text-slider-a" href="https://2smart.pt/">2Smart App: o
                            sucesso que marcou o nosso último evento</a></div>
                    <div class="flex text-slide"><a class="text-slider-a"
                            href="https://store.exportech.com.pt/configurador/">Experimente o Nosso Configurador de
                            Bastidores!</a></div>
                    <div class="flex text-slide"><a class="text-slider-a"
                            href="https://store.exportech.com.pt/product-category/sistemas-incendio/convencional/centrais-convencional/?marcas=tecnimaster">Novos
                            produtos Firetec - Controle total na Prevenção de incendios!</a></div>
                </div>
            </div>

            <?php
            $user = wp_get_current_user();
            echo $user->ID == 0 ? "" : '<div class="whatsappwidget"><button class="btn toggle-chat"><div class="icon"><i class="fa-brands fa-whatsapp"></i></div></button><ul class="users-list" id="whatsapp-widget"><div class="header-chat"><div class="icon"><i class="fa-regular fa-message"></i></div><div class="txt"><h5>Iniciar uma conversa</h5><div>Olá, escolha um dos nossos comerciais abaixo para conversar no <strong>Whatsapp</strong></div></div></div><div class="tab-container"><div class="tab-items"><div class="tab active">Lisboa</div><div class="tab">Porto</div><div class="tab">Madeira</div></div><div class="list active lisboa"><small>A equipa normalmente responde em 24 Horas *</small><a href="https://api.whatsapp.com/send?phone=351210353555" target="_blank" rel="noopener noreferrer"><li><div class="avatar"><img src="https://exportech.com.pt/static/media/Paulo.6afb2899b6a1a53e2b95.png" alt=""></div><div class="text"><h5>Paulo Ferreira</h5><div>Gestor de clientes <span>(Lisboa)</span></div></div></li></a><a href="https://api.whatsapp.com/send?phone=351934444006" target="_blank" rel="noopener noreferrer"><li><div class="avatar"><img src="https://exportech.com.pt/static/media/Jose.23e0da7968c1789efbad.png" alt=""></div><div class="text"><h5>José Carvalho</h5><div>Gestor de clientes <span>(Lisboa)</span></div></div></li></a></div><div class="list porto"><small>A equipa normalmente responde em 24 Horas *</small><a href="https://api.whatsapp.com/send?phone=351936480464" target="_blank" rel="noopener noreferrer"><li><div class="avatar"><img src="https://exportech.com.pt/static/media/Germano.30dac6f256368bc0272b.png" alt=""></div><div class="text"><h5>Germano Oliveira</h5><div>Gestor de clientes <span>(Porto)</span></div></div></li></a><a href="https://api.whatsapp.com/send?phone=351935555002" target="_blank" rel="noopener noreferrer"><li><div class="avatar"><img src="https://exportech.com.pt/static/media/bruno.80078cf32110e59ca303.png" alt=""></div><div class="text"><h5>Bruno Pimenta</h5><div>Gestor de clientes <span>(Porto)</span></div></div></li></a></div><div class="list madeira"><small>A equipa normalmente responde em 24 Horas *</small><a href="https://api.whatsapp.com/send?phone=351968084534" target="_blank" rel="noopener noreferrer"><li><div class="avatar"><img src="https://exportech.com.pt/static/media/Rui.c962b76a4a64ad7b36b7.png" alt=""></div><div class="text"><h5>Rui Guedelha</h5><div>Gestor de clientes <span>(Madeira)</span></div></div></li></a></div></div></ul></div>';
            ?>

            <?php do_action('nasa_mobile_header'); ?>
            <div class="row nasa-hide-for-mobile">
                <div class="large-12 columns nasa-wrap-event-search">
                    <div class="nasa-header-flex nasa-elements-wrap">
                        <!-- Group icon header -->
                        <div class="nasa-flex-item-1-3 first-columns nasa-flex">
                            <a class="nasa-menu-off nasa-icon margin-right-10 rtl-margin-right-0 rtl-margin-left-10 nasa-flex black"
                                href="javascript:void(0);" rel="nofollow">
                                <svg viewBox="0 0 32 32" width="28" height="28" fill="black">
                                    <path
                                        d="M 4 7 L 4 9 L 28 9 L 28 7 Z M 4 15 L 4 17 L 28 17 L 28 15 Z M 4 23 L 4 25 L 28 25 L 28 23 Z" />
                                </svg>
                            </a>
                            <!-- Logo -->
                            <div class="nasa-flex-item-1-2-0 text-center margin-left-10">
                                <?php echo elessi_logo(); ?>
                            </div>


                            <!-- Sub menu in header -->
                            <div class="wide-nav menu-items ml-2 menu-sec"
                                style="margin:30px; margin-right:5px; font-weight:500; color:black;min-width:100px;">
                                <a href="http://2smart.pt" target="_blank" rel="noopener noreferrer">2Smart HR</a>
                            </div>



                            <div>
                                <a href="/configurador" class="config-box">
                                    <i class="fa-solid fa-hand-sparkles" style="margin-right:5px; "></i> Configurador
                                </a>
                            </div>

                        </div>

                        <div class="nasa-flex-item-1-2">
                            <div class="nasa-header-search-wrap nasa-search-relative  ">
                                <?php echo elessi_search('full'); ?>
                            </div>
                        </div>

                        <!-- Group icon header -->
                        <div class="nasa-flex-item-1-3 nav-icons-area">

                            <?php echo $nasa_header_icons; ?>
                        </div>

                    </div>


                </div>
            </div>

            <!-- Main menu -->
            <div class="nasa-off-canvas hidden-tag">
                <?php elessi_get_main_menu(); ?>

                <?php do_action('nasa_multi_lc'); ?>
            </div>

            <?php if (defined('NASA_TOP_FILTER_CATS') && NASA_TOP_FILTER_CATS): ?>
            <div class="nasa-top-cat-filter-wrap">
                <?php echo elessi_get_all_categories(false, true); ?>
                <a href="javascript:void(0);" title="<?php esc_attr_e('Close', 'elessi-theme'); ?>"
                    class="nasa-close-filter-cat nasa-stclose nasa-transition" rel="nofollow"></a>
            </div>
            <?php endif; ?>
        </div>
    </div>


</div>
<div style="margin-top:90px;"></div>
</section>
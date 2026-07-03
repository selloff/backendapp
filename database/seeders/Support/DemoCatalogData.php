<?php

namespace Database\Seeders\Support;

/**
 * Curated demo marketplace catalog — realistic titles, prices, and Unsplash product photos.
 */
final class DemoCatalogData
{
    public const VENDORS = [
        'vendor' => [
            'email' => 'vendor@selloff.test',
            'first_name' => 'Demo',
            'last_name' => 'Vendor',
            'slug' => 'demo-vendor',
            'shop_name' => 'Demo Electronics',
            'shop_slug' => 'demo-electronics',
            'commission_rate' => 5,
            'avatar' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=200&h=200&fit=crop',
        ],
        'vendor2' => [
            'email' => 'vendor2@selloff.test',
            'first_name' => 'Ada',
            'last_name' => 'Fashion',
            'slug' => 'demo-vendor-fashion',
            'shop_name' => 'Demo Fashion Hub',
            'shop_slug' => 'demo-fashion-hub',
            'commission_rate' => 8,
            'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200&h=200&fit=crop',
        ],
        'vendor3' => [
            'email' => 'vendor3@selloff.test',
            'first_name' => 'Chidi',
            'last_name' => 'Okoro',
            'slug' => 'demo-vendor-home',
            'shop_name' => 'Lagos Home & Living',
            'shop_slug' => 'lagos-home-living',
            'commission_rate' => 6,
            'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop',
        ],
        'vendor4' => [
            'email' => 'vendor4@selloff.test',
            'first_name' => 'Ngozi',
            'last_name' => 'Bello',
            'slug' => 'demo-vendor-beauty',
            'shop_name' => 'Glow Beauty NG',
            'shop_slug' => 'glow-beauty-ng',
            'commission_rate' => 7,
            'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=200&h=200&fit=crop',
        ],
        'vendor5' => [
            'email' => 'vendor5@selloff.test',
            'first_name' => 'Tunde',
            'last_name' => 'Adeyemi',
            'slug' => 'demo-vendor-sports',
            'shop_name' => 'FitLife Sports',
            'shop_slug' => 'fitlife-sports',
            'commission_rate' => 6,
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop',
        ],
        'vendor6' => [
            'email' => 'vendor6@selloff.test',
            'first_name' => 'Amaka',
            'last_name' => 'Eze',
            'slug' => 'demo-vendor-books',
            'shop_name' => 'PageTurner Books',
            'shop_slug' => 'pageturner-books',
            'commission_rate' => 5,
            'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=200&h=200&fit=crop',
        ],
    ];

    public const CATEGORIES = [
        'phones-and-tablets' => [
            'name' => 'Smartphones & Tablets',
            'description' => 'Mobile phones, tablets, and accessories',
            'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&h=400&fit=crop',
            'homepage_order' => 1,
        ],
        'laptops' => [
            'name' => 'Laptops and Computers',
            'description' => 'Notebooks, desktops, and peripherals',
            'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=400&h=400&fit=crop',
            'homepage_order' => 2,
        ],
        'fashion' => [
            'name' => 'Fashion',
            'description' => 'Clothing, shoes, and accessories',
            'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=400&h=400&fit=crop',
            'homepage_order' => 3,
        ],
        'home-living' => [
            'name' => 'Home & Living',
            'description' => 'Furniture, décor, and kitchen essentials',
            'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400&h=400&fit=crop',
            'homepage_order' => 4,
        ],
        'beauty' => [
            'name' => 'Beauty & Personal Care',
            'description' => 'Skincare, makeup, and grooming',
            'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=400&h=400&fit=crop',
            'homepage_order' => 5,
        ],
        'sports' => [
            'name' => 'Sports & Outdoors',
            'description' => 'Fitness gear, sportswear, and equipment',
            'image' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=400&h=400&fit=crop',
            'homepage_order' => 6,
        ],
        'books' => [
            'name' => 'Books & Media',
            'description' => 'Books, stationery, and learning materials',
            'image' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=400&h=400&fit=crop',
            'homepage_order' => 7,
        ],
        'accessories' => [
            'name' => 'Electronics Accessories',
            'description' => 'Chargers, cases, audio, and cables',
            'image' => 'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=400&h=400&fit=crop',
            'homepage_order' => 8,
        ],
    ];

    public const SUBCATEGORIES = [
        'phones-and-tablets' => [
            'smartphones' => [
                'name' => 'Smartphones',
                'description' => 'Mobile phones and accessories',
            ],
            'tablets' => [
                'name' => 'Tablets',
                'description' => 'Tablets and e-readers',
            ],
        ],
    ];

    /**
     * @return list<array{
     *   sku: string,
     *   title: string,
     *   price: int,
     *   vendor: string,
     *   category: string,
     *   brand: string,
     *   images: list<string>,
     *   short_description?: string,
     *   listing_type?: string,
     *   price_discounted?: int,
     *   is_promoted?: bool,
     * }>
     */
    public static function products(): array
    {
        return [
            // Demo Electronics — phones & laptops (keep legacy SKUs for tests)
            ['sku' => 'DEMO-PHONE-1', 'title' => 'Samsung Galaxy A54 5G', 'price' => 89999, 'vendor' => 'vendor', 'category' => 'smartphones', 'brand' => 'Samsung', 'images' => [
                'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&h=800&fit=crop',
                'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=800&h=800&fit=crop',
            ], 'short_description' => '128GB, dual SIM, 50MP camera', 'price_discounted' => 79999, 'is_promoted' => true],
            ['sku' => 'DEMO-AUDIO-1', 'title' => 'Sony WF-C500 Wireless Earbuds', 'price' => 14999, 'vendor' => 'vendor', 'category' => 'accessories', 'brand' => 'Sony', 'images' => [
                'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&h=800&fit=crop',
            ], 'short_description' => '12hr battery, IPX4 splash resistant'],
            ['sku' => 'DEMO-CLASSIFIED-1', 'title' => 'Used iPhone 12 64GB', 'price' => 185000, 'vendor' => 'vendor', 'category' => 'smartphones', 'brand' => 'Apple', 'listing_type' => 'ordinary_listing', 'images' => [
                'https://images.unsplash.com/photo-1632661674417-df8360a34e23?w=800&h=800&fit=crop',
            ], 'short_description' => 'Clean UK used, battery health 87%'],
            ['sku' => 'DEMO-CASE-1', 'title' => 'Spigen Tough Armor Phone Case', 'price' => 4999, 'vendor' => 'vendor', 'category' => 'accessories', 'brand' => 'Spigen', 'images' => [
                'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=800&h=800&fit=crop',
            ], 'short_description' => 'Shock-absorbing dual-layer protection'],
            ['sku' => 'DEMO-LAPTOP-1', 'title' => 'HP Pavilion 15 Ultrabook', 'price' => 249999, 'vendor' => 'vendor', 'category' => 'laptops', 'brand' => 'HP', 'images' => [
                'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=800&h=800&fit=crop',
            ], 'short_description' => 'Intel i5, 16GB RAM, 512GB SSD'],
            ['sku' => 'DEMO-LAPTOP-2', 'title' => 'ASUS ROG Strix Gaming Laptop', 'price' => 389999, 'vendor' => 'vendor', 'category' => 'laptops', 'brand' => 'ASUS', 'images' => [
                'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=800&h=800&fit=crop',
            ], 'short_description' => 'RTX 4060, 144Hz display', 'listing_type' => 'bidding'],
            ['sku' => 'DEMO-TAB-1', 'title' => 'Lenovo Tab P11 Tablet', 'price' => 89999, 'vendor' => 'vendor', 'category' => 'tablets', 'brand' => 'Lenovo', 'images' => [
                'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=800&h=800&fit=crop',
            ], 'short_description' => '11" 2K display, quad speakers'],
            ['sku' => 'DEMO-WATCH-1', 'title' => 'Apple Watch SE (2nd Gen)', 'price' => 119999, 'vendor' => 'vendor', 'category' => 'accessories', 'brand' => 'Apple', 'images' => [
                'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=800&h=800&fit=crop',
            ], 'short_description' => 'Fitness tracking, crash detection'],
            ['sku' => 'DEMO-CHARGER-1', 'title' => 'Anker 65W GaN USB-C Charger', 'price' => 8999, 'vendor' => 'vendor', 'category' => 'accessories', 'brand' => 'Anker', 'images' => [
                'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=800&h=800&fit=crop',
            ], 'short_description' => 'Fast charge laptop + phone'],
            ['sku' => 'DEMO-PHONE-2', 'title' => 'iPhone 13 128GB', 'price' => 349999, 'vendor' => 'vendor', 'category' => 'smartphones', 'brand' => 'Apple', 'images' => [
                'https://images.unsplash.com/photo-1632661674417-df8360a34e23?w=800&h=800&fit=crop',
                'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&h=800&fit=crop',
            ], 'short_description' => 'A15 Bionic, Ceramic Shield'],
            ['sku' => 'DEMO-PHONE-3', 'title' => 'Tecno Camon 20 Pro', 'price' => 64999, 'vendor' => 'vendor', 'category' => 'smartphones', 'brand' => 'Tecno', 'images' => [
                'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=800&h=800&fit=crop',
                'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=800&h=800&fit=crop',
            ], 'short_description' => '64MP portrait camera, 5000mAh'],
            ['sku' => 'DEMO-MONITOR-1', 'title' => 'Dell 27" 4K USB-C Monitor', 'price' => 179999, 'vendor' => 'vendor', 'category' => 'laptops', 'brand' => 'Dell', 'images' => [
                'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=800&h=800&fit=crop',
            ], 'short_description' => 'IPS panel, 99% sRGB'],
            ['sku' => 'DEMO-KEYBOARD-1', 'title' => 'Logitech MX Keys Mechanical', 'price' => 34999, 'vendor' => 'vendor', 'category' => 'accessories', 'brand' => 'Logitech', 'images' => [
                'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&h=800&fit=crop',
            ], 'short_description' => 'Wireless, backlit, multi-device'],
            ['sku' => 'DEMO-SPEAKER-1', 'title' => 'JBL Flip 6 Bluetooth Speaker', 'price' => 29999, 'vendor' => 'vendor', 'category' => 'accessories', 'brand' => 'JBL', 'images' => [
                'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=800&h=800&fit=crop',
            ], 'short_description' => 'IP67 waterproof, 12hr playtime'],
            ['sku' => 'DEMO-CAMERA-1', 'title' => 'Canon EOS M50 Mark II', 'price' => 429999, 'vendor' => 'vendor', 'category' => 'accessories', 'brand' => 'Canon', 'images' => [
                'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800&h=800&fit=crop',
            ], 'short_description' => '24.1MP mirrorless, 4K video'],

            // Demo Fashion Hub
            ['sku' => 'DEMO-FASHION-1', 'title' => 'Ankara Print Midi Dress', 'price' => 24999, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=800&h=800&fit=crop',
            ], 'short_description' => 'Handmade wax print, sizes S–XL'],
            ['sku' => 'DEMO-FASHION-2', 'title' => 'Leather Crossbody Sandals', 'price' => 12999, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1603487742031-3adcc45b1d69?w=800&h=800&fit=crop',
            ], 'short_description' => 'Genuine leather, cushioned sole'],
            ['sku' => 'DEMO-FASHION-3', 'title' => 'Men\'s Linen Casual Shirt', 'price' => 8999, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1596755094514-f87e34085b56?w=800&h=800&fit=crop',
            ], 'short_description' => 'Breathable linen blend'],
            ['sku' => 'DEMO-FASHION-4', 'title' => 'Women\'s High-Waist Jeans', 'price' => 15999, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=800&h=800&fit=crop',
            ], 'short_description' => 'Stretch denim, classic fit'],
            ['sku' => 'DEMO-FASHION-5', 'title' => 'Kente Pattern Headwrap', 'price' => 5999, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1558171813-4c088754af6f?w=800&h=800&fit=crop',
            ], 'short_description' => 'Authentic woven print'],
            ['sku' => 'DEMO-FASHION-6', 'title' => 'Unisex Canvas Sneakers', 'price' => 18999, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=800&h=800&fit=crop',
            ], 'short_description' => 'Lightweight, rubber outsole'],
            ['sku' => 'DEMO-FASHION-7', 'title' => 'Beaded Statement Necklace', 'price' => 7499, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=800&h=800&fit=crop',
            ], 'short_description' => 'Handcrafted glass beads'],
            ['sku' => 'DEMO-FASHION-8', 'title' => 'Aso-Oke Two-Piece Set', 'price' => 44999, 'vendor' => 'vendor2', 'category' => 'fashion', 'brand' => 'Demo Style', 'images' => [
                'https://images.unsplash.com/photo-1566174053879-31528523f8ae?w=800&h=800&fit=crop',
            ], 'short_description' => 'Traditional occasion wear'],

            // Lagos Home & Living
            ['sku' => 'DEMO-HOME-1', 'title' => 'Scandinavian Fabric Sofa', 'price' => 189999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=800&fit=crop',
            ], 'short_description' => '3-seater, removable covers'],
            ['sku' => 'DEMO-HOME-2', 'title' => 'Ceramic Dinnerware Set (16pc)', 'price' => 24999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1578749556568-bc2c40f68d57?w=800&h=800&fit=crop',
            ], 'short_description' => 'Microwave and dishwasher safe'],
            ['sku' => 'DEMO-HOME-3', 'title' => 'Memory Foam Pillow Pair', 'price' => 12999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1584100936595-c0654b55a2e2?w=800&h=800&fit=crop',
            ], 'short_description' => 'Cooling gel layer'],
            ['sku' => 'DEMO-HOME-4', 'title' => 'LED Floor Lamp', 'price' => 18999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=800&h=800&fit=crop',
            ], 'short_description' => 'Dimmable warm white'],
            ['sku' => 'DEMO-HOME-5', 'title' => 'Non-Stick Cookware Set', 'price' => 34999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=800&h=800&fit=crop',
            ], 'short_description' => '10-piece induction ready'],
            ['sku' => 'DEMO-HOME-6', 'title' => 'Woven Storage Basket Set', 'price' => 8999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1595428774223-ef5262410190?w=800&h=800&fit=crop',
            ], 'short_description' => 'Set of 3 natural seagrass'],
            ['sku' => 'DEMO-HOME-7', 'title' => 'Queen Size Duvet Cover', 'price' => 15999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&h=800&fit=crop',
            ], 'short_description' => '400 thread count cotton'],
            ['sku' => 'DEMO-HOME-8', 'title' => 'Indoor Plant Stand (3-tier)', 'price' => 11999, 'vendor' => 'vendor3', 'category' => 'home-living', 'brand' => 'Lagos Home', 'images' => [
                'https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=800&h=800&fit=crop',
            ], 'short_description' => 'Bamboo frame, holds 6 pots'],

            // Glow Beauty NG
            ['sku' => 'DEMO-BEAUTY-1', 'title' => 'Vitamin C Brightening Serum', 'price' => 8999, 'vendor' => 'vendor4', 'category' => 'beauty', 'brand' => 'Glow NG', 'images' => [
                'https://images.unsplash.com/photo-1620916567198-59b4aa5d9c42?w=800&h=800&fit=crop',
            ], 'short_description' => '20% vitamin C, hyaluronic acid'],
            ['sku' => 'DEMO-BEAUTY-2', 'title' => 'Shea Butter Body Cream', 'price' => 4999, 'vendor' => 'vendor4', 'category' => 'beauty', 'brand' => 'Glow NG', 'images' => [
                'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=800&h=800&fit=crop',
            ], 'short_description' => 'Unrefined Ghana shea butter'],
            ['sku' => 'DEMO-BEAUTY-3', 'title' => 'Matte Liquid Lipstick Set', 'price' => 6999, 'vendor' => 'vendor4', 'category' => 'beauty', 'brand' => 'Glow NG', 'images' => [
                'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=800&h=800&fit=crop',
            ], 'short_description' => '6 shades, 8hr wear'],
            ['sku' => 'DEMO-BEAUTY-4', 'title' => 'Natural Hair Growth Oil', 'price' => 5999, 'vendor' => 'vendor4', 'category' => 'beauty', 'brand' => 'Glow NG', 'images' => [
                'https://images.unsplash.com/photo-1608248543801-ba977f7f8d76?w=800&h=800&fit=crop',
            ], 'short_description' => 'Castor, rosemary, peppermint'],
            ['sku' => 'DEMO-BEAUTY-5', 'title' => 'SPF 50 Sunscreen Lotion', 'price' => 7499, 'vendor' => 'vendor4', 'category' => 'beauty', 'brand' => 'Glow NG', 'images' => [
                'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?w=800&h=800&fit=crop',
            ], 'short_description' => 'Broad spectrum, non-greasy'],
            ['sku' => 'DEMO-BEAUTY-6', 'title' => 'Electric Facial Cleansing Brush', 'price' => 14999, 'vendor' => 'vendor4', 'category' => 'beauty', 'brand' => 'Glow NG', 'images' => [
                'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=800&h=800&fit=crop',
            ], 'short_description' => '3 speeds, waterproof'],

            // FitLife Sports
            ['sku' => 'DEMO-SPORT-1', 'title' => 'Adjustable Dumbbell Set 24kg', 'price' => 54999, 'vendor' => 'vendor5', 'category' => 'sports', 'brand' => 'FitLife', 'images' => [
                'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?w=800&h=800&fit=crop',
            ], 'short_description' => 'Quick-change weight plates'],
            ['sku' => 'DEMO-SPORT-2', 'title' => 'Yoga Mat with Carrying Strap', 'price' => 8999, 'vendor' => 'vendor5', 'category' => 'sports', 'brand' => 'FitLife', 'images' => [
                'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=800&h=800&fit=crop',
            ], 'short_description' => '6mm TPE, non-slip texture'],
            ['sku' => 'DEMO-SPORT-3', 'title' => 'Men\'s Running Shoes', 'price' => 22999, 'vendor' => 'vendor5', 'category' => 'sports', 'brand' => 'FitLife', 'images' => [
                'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&h=800&fit=crop',
            ], 'short_description' => 'Breathable mesh, cushioned sole'],
            ['sku' => 'DEMO-SPORT-4', 'title' => 'Resistance Bands Kit', 'price' => 6999, 'vendor' => 'vendor5', 'category' => 'sports', 'brand' => 'FitLife', 'images' => [
                'https://images.unsplash.com/photo-1598289431512-97c090cc4edc?w=800&h=800&fit=crop',
            ], 'short_description' => '5 levels, door anchor included'],
            ['sku' => 'DEMO-SPORT-5', 'title' => 'Insulated Sports Water Bottle', 'price' => 4999, 'vendor' => 'vendor5', 'category' => 'sports', 'brand' => 'FitLife', 'images' => [
                'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=800&h=800&fit=crop',
            ], 'short_description' => '750ml, keeps cold 24hr'],
            ['sku' => 'DEMO-SPORT-6', 'title' => 'Football Size 5 (FIFA Quality)', 'price' => 9999, 'vendor' => 'vendor5', 'category' => 'sports', 'brand' => 'FitLife', 'images' => [
                'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=800&h=800&fit=crop',
            ], 'short_description' => 'Thermally bonded panels'],

            // PageTurner Books
            ['sku' => 'DEMO-BOOK-1', 'title' => 'Atomic Habits — James Clear', 'price' => 5999, 'vendor' => 'vendor6', 'category' => 'books', 'brand' => 'PageTurner', 'images' => [
                'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=800&h=800&fit=crop',
            ], 'short_description' => 'Paperback, international edition'],
            ['sku' => 'DEMO-BOOK-2', 'title' => 'Things Fall Apart — Chinua Achebe', 'price' => 4499, 'vendor' => 'vendor6', 'category' => 'books', 'brand' => 'PageTurner', 'images' => [
                'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=800&h=800&fit=crop',
            ], 'short_description' => 'Classic African literature'],
            ['sku' => 'DEMO-BOOK-3', 'title' => 'Moleskine Classic Notebook', 'price' => 7999, 'vendor' => 'vendor6', 'category' => 'books', 'brand' => 'PageTurner', 'images' => [
                'https://images.unsplash.com/photo-1531346878377-a5be20888e57?w=800&h=800&fit=crop',
            ], 'short_description' => 'A5 ruled, hard cover'],
            ['sku' => 'DEMO-BOOK-4', 'title' => 'Children\'s Illustrated Atlas', 'price' => 8999, 'vendor' => 'vendor6', 'category' => 'books', 'brand' => 'PageTurner', 'images' => [
                'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=800&h=800&fit=crop',
            ], 'short_description' => 'Ages 6–12, full colour'],
            ['sku' => 'DEMO-BOOK-5', 'title' => 'Premium Ballpoint Pen Set', 'price' => 3499, 'vendor' => 'vendor6', 'category' => 'books', 'brand' => 'PageTurner', 'images' => [
                'https://images.unsplash.com/photo-1583485088034-697b5f153050?w=800&h=800&fit=crop',
            ], 'short_description' => 'Pack of 12, smooth ink'],
        ];
    }
}

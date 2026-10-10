-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 05, 2026 at 11:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `spirited_finds_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_id`, `quantity`) VALUES
(3, 3, 2, 1),
(4, 3, 15, 2);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Enchanted Pages', 'Ghibli books and journals'),
(2, 'Spirit Threads', 'Ghibli apparel and clothes'),
(3, 'Cuddly Companions', 'Plushies and toys'),
(4, 'Magic Trinkets', 'Accessories and keychains'),
(5, 'Ghibli Gems', 'Special edition collectibles');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `message_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `status`, `created_at`) VALUES
(1, 3, 400.00, 'Pending', '2026-10-05 09:23:59'),
(2, 3, 900.00, 'Completed', '2026-10-05 09:40:29');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 3, 1, 300.00),
(2, 2, 2, 1, 800.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `rating` int(11) DEFAULT 5,
  `stock` int(11) DEFAULT 10,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_special` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `title`, `description`, `price`, `image`, `rating`, `stock`, `is_featured`, `is_special`, `created_at`) VALUES
(1, 2, 'Sweatshirt', 'Cozy sweatshirt featuring Ghibli designs.', 1000.00, './image/Sweatshirt.png', 3, 15, 1, 0, '2026-09-25 09:14:24'),
(2, 2, 'Ghibli Apron', 'Stylish apron for cooking with a Ghibli twist.', 800.00, './image/Studio Ghibli Apron.png', 3, 9, 1, 0, '2026-09-25 09:14:24'),
(3, 4, 'Stickers', 'Fun sticker set featuring Ghibli characters.', 300.00, './image/Stickers.png', 4, 49, 1, 0, '2026-09-25 09:14:24'),
(4, 4, 'Plush Tote Bag', 'Soft tote bag perfect for everyday use.', 700.00, './image/Plush Tote Bag.png', 5, 20, 1, 0, '2026-09-25 09:14:24'),
(5, 1, 'Notebooks', 'Cute notebooks featuring Ghibli artwork.', 400.00, './image/Notebooks.png', 3, 30, 1, 0, '2026-09-25 09:14:24'),
(6, 4, 'Kikis Stamp', 'Wooden stamp inspired by Kiki\'s Delivery Service.', 500.00, './image/Kiki_s Delivery Wooden Stamp.png', 3, 25, 1, 0, '2026-09-25 09:14:24'),
(7, 4, 'Howl\'s Nails', 'Stylish nail art inspired by Howl\'s Moving Castle.', 350.00, './image/How_ls Moving Castle Nail.png', 4, 40, 1, 0, '2026-09-25 09:14:24'),
(8, 4, 'Glass Cup', 'Glass cup with lid and straw for drinks.', 600.00, './image/Glass Cup with Lid & Straw.png', 5, 18, 1, 0, '2026-09-25 09:14:24'),
(9, 2, 'Ghibli Shoes', 'Customizable shoes w/ Studio Ghibli designs.', 1500.00, './image/Studio Ghibli Customize Shoes.png', 5, 10, 0, 1, '2026-09-25 09:14:24'),
(10, 5, 'Totoro Vinyl', 'Sound book vinyl featuring My Neighbor Totoro.', 1200.00, './image/My Neighbor Totoro Souns Book Vinyl.png', 5, 8, 0, 1, '2026-09-25 09:14:24'),
(11, 4, 'Kiki\'s Bag', 'Adorable lunch bag inspired by Kiki\'s Delivery Service.', 800.00, './image/Kiki_s Delivery Service Lunch Bag.png', 5, 12, 0, 1, '2026-09-25 09:14:24'),
(12, 4, 'Ghibli Keycaps', 'Fun keycaps featuring Ghibli characters.', 600.00, './image/Ghibli Keycaps.png', 5, 25, 0, 1, '2026-09-25 09:14:24'),
(13, 1, 'Arrietty', 'A Borrower befriends a human, risking her world.', 419.00, 'arrietty.jpg', 3, 20, 0, 0, '2026-09-25 09:38:09'),
(14, 1, 'Moving Castle', 'A cursed girl finds safety in a wizard’s castle.', 643.00, 'howls.jpg', 3, 20, 0, 0, '2026-09-25 09:38:09'),
(15, 1, 'Totoro', 'Sisters befriend the gentle forest spirit Totoro.', 475.00, 'totoro.jpg', 4, 20, 0, 0, '2026-09-25 09:38:09'),
(16, 1, 'Wind Rises', 'Jiro dreams of flight during wartime.', 559.00, 'windrises.jpg', 5, 20, 0, 0, '2026-09-25 09:38:09'),
(17, 1, 'Ponyo', 'A goldfish becomes human, starting an adventure.', 503.00, 'ponyo.jpg', 3, 20, 0, 0, '2026-09-25 09:38:09'),
(18, 1, 'Princess Mononoke', 'A warrior fights to save nature.', 529.00, 'mononoke.jpg', 3, 20, 0, 0, '2026-09-25 09:38:09'),
(19, 1, 'Spirited Away', 'A girl navigates a magical world to save her parents.', 615.00, 'spiritedaway.jpg', 4, 20, 0, 0, '2026-09-25 09:38:09'),
(20, 1, 'Nausicaä', 'A princess fights to protect her toxic world.', 545.00, 'nausicaa.jpg', 5, 20, 0, 0, '2026-09-25 09:38:09'),
(21, 3, 'Warawara Plushie', 'Soft and cuddly Warawara plush toy.', 450.00, './image/Warawara Plushie.png', 3, 15, 1, 0, '2026-09-25 15:04:33'),
(22, 3, 'Heron Plushie', 'Adorable heron plush, perfect for fans.', 400.00, './image/Heron Plushie.png', 3, 15, 1, 0, '2026-09-25 15:04:33'),
(23, 3, 'Soot Sprite', 'Cute Soot Sprite plushie for cozy cuddles.', 300.00, './image/Soot Sprite.png', 4, 20, 1, 0, '2026-09-25 15:04:33'),
(24, 3, 'Calcifer Plushie', 'Fiery Calcifer plushie to brighten your day.', 1000.00, './image/Calcifer Plushie.png', 5, 10, 1, 0, '2026-09-25 15:04:33'),
(25, 5, 'Card Holder', 'Simple and stylish card holder.', 150.00, './image/Card Holder.png', 3, 20, 0, 0, '2026-09-25 15:07:20'),
(26, 5, 'Soft Carpet', 'Cozy totoro carpet for your space.', 600.00, './image/Carpet.png', 3, 15, 0, 0, '2026-09-25 15:07:20'),
(27, 5, 'Cookie Jar', 'Adorable cookie jar for storage.', 300.00, './image/Cookie Jar.png', 4, 15, 0, 0, '2026-09-25 15:07:20'),
(28, 5, 'Ghibli Tote', 'Ghibli Studio-themed tote bag.', 350.00, './image/Ghibli Studio Tote Bag.png', 5, 25, 0, 0, '2026-09-25 15:07:20'),
(29, 5, 'Totoro Lamp', 'Cute Totoro-themed lamp by Goosemans.', 750.00, './image/Goosemans Totoro Lamp.png', 3, 10, 0, 0, '2026-09-25 15:07:20'),
(30, 5, 'Ghibli Mug', 'Ceramic mug with Ghibli design.', 200.00, './image/Mug.png', 3, 30, 0, 0, '2026-09-25 15:07:20'),
(31, 5, 'Purple Bottle', 'Stylish kiki\'s purple water bottle.', 250.00, './image/Purple Bottle Water.png', 4, 20, 0, 0, '2026-09-25 15:07:20'),
(32, 5, 'Totoro Blanket', 'Warm and comfy Totoro blanket.', 500.00, './image/Totoro Blanket.png', 5, 15, 0, 0, '2026-09-25 15:07:20'),
(33, 4, 'Ghibli Jibbitz', 'Fun Ghibli-themed Jibbitz for your shoes.', 200.00, './image/Ghibli Jibbitz.png', 3, 20, 0, 0, '2026-09-25 15:22:55'),
(34, 4, 'Hair Accessories', 'Ghibli-themed hair accessories.', 250.00, './image/Hair Accessories.png', 3, 20, 0, 0, '2026-09-25 15:22:55'),
(35, 4, 'No-Face Towel', 'Soft dress towel featuring No-Face.', 600.00, './image/No-Face Dress Towel.png', 4, 20, 0, 0, '2026-09-25 15:22:55'),
(36, 4, 'Ponyo Charm', 'Charming Ponyo keychain for added flair.', 175.00, './image/Ponyo Charm Keychain.png', 5, 20, 0, 0, '2026-09-25 15:22:55'),
(37, 2, 'Sprite Cardigan', 'Cozy cardigan with playful soot sprites.', 500.00, './image/Soot Sprites Cardigan.png', 3, 20, 0, 0, '2026-09-25 15:24:24'),
(38, 2, 'Calcifer Tee', 'Stylish tee featuring fiery Calcifer.', 350.00, './image/Calcifer T-Shirt.png', 3, 20, 0, 0, '2026-09-25 15:24:24'),
(39, 2, 'Howl\'s Scenic', 'Stunning print inspired by Howl\'s Moving Castle.', 600.00, './image/HMC Scenic.png', 4, 20, 0, 0, '2026-09-25 15:24:24'),
(40, 2, 'Mononoke Scenic', 'Breathtaking artwork from Princess Mononoke.', 500.00, './image/Princess Mononoke Scenic T-Shirt.png', 5, 20, 0, 0, '2026-09-25 15:24:24');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `role`, `created_at`) VALUES
(1, 'Kiki\'s', 'Delivery', 'admin@spiritedfinds.com', '09123456789', 'admin123', 'admin', '2026-09-25 16:08:39'),
(2, '', '', 'customer@spiritedfinds.com', '09987654321', '$2y$10$q2/9p23iElnP7M5LpPvh8eIeA1Ept3yN6v9uCxlm2/gX6u2s4Z63G', 'customer', '2026-09-25 16:08:39'),
(3, 'John Mark', 'Reyes', 'jmvreyes@gmail.com', '09999999999', '$2y$10$e8ReDadw19X9Cul4ijtPeeyy8eX7cZgtHH.M4CW9ry35vwj49PZBq', 'customer', '2026-10-05 08:41:42');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

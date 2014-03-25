--
-- Table structure for table `contacts`
--
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE IF NOT EXISTS `contacts` (
  `user_id` int(10) unsigned NOT NULL,
  `contact_user_id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `email` varchar(64) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `is_whitelist` tinyint(1) unsigned NOT NULL,
  `is_blocked` tinyint(1) unsigned NOT NULL,
  UNIQUE KEY `user_id_2` (`user_id`,`contact_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `is_read` tinyint(1) unsigned NOT NULL,
  `is_important` tinyint(1) unsigned NOT NULL,
  `is_draft` tinyint(1) unsigned NOT NULL,
  `sent_date` datetime NOT NULL,
  `read_date` datetime NOT NULL,
  `expire_date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

DROP TABLE IF EXISTS `participants`;
CREATE TABLE IF NOT EXISTS `participants` (
  `message_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `is_sender` tinyint(1) unsigned NOT NULL,
  KEY `message_id` (`message_id`,`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `merch_payment_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `amount` double(4,2) unsigned NOT NULL,
  `approval_code` varchar(16) NOT NULL,
  `payment_date` datetime NOT NULL,
  PRIMARY KEY (`merch_payment_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_levels`
--

DROP TABLE IF EXISTS `subscription_levels`;
CREATE TABLE IF NOT EXISTS `subscription_levels` (
  `id` mediumint(8) unsigned NOT NULL,
  `name` varchar(16) NOT NULL,
  `description` varchar(64) NOT NULL,
  `cpm` decimal(4,2) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `sync_device`
--

DROP TABLE IF EXISTS `sync_device`;
CREATE TABLE IF NOT EXISTS `sync_device` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `signature` varchar(64) NOT NULL,
  `label` varchar(32) NOT NULL,
  `description` varchar(32) NOT NULL,
  `is_initialized` tinyint(1) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `token_expires` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `sync_queue`
--

DROP TABLE IF EXISTS `sync_queue`;
CREATE TABLE IF NOT EXISTS `sync_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` tinyint(3) unsigned NOT NULL,
  `device_id` tinyint(3) unsigned NOT NULL,
  `date_added` datetime NOT NULL,
  `tablename` varchar(12) NOT NULL,
  `pk` int(10) unsigned NOT NULL,
  `vals` text NOT NULL,
  `is_pulled` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(16) NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,
  `email` varchar(64) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `pass` varchar(16) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_last_pass_update` datetime NOT NULL,
  `language` varchar(2) NOT NULL,
  `notifications_on` tinyint(1) unsigned NOT NULL,
  `whitelist_only` tinyint(1) unsigned NOT NULL,
  `enable_panic` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
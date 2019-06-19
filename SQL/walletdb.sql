-- phpMyAdmin SQL Dump
-- version 4.7.9
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 19, 2019 at 05:28 PM
-- Server version: 5.7.21
-- PHP Version: 5.6.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `walletdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
CREATE TABLE IF NOT EXISTS `assets` (
  `walletid` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Wallet ID ',
  `label` varchar(255) NOT NULL COMMENT 'Wallet label',
  `amount` bigint(20) NOT NULL COMMENT 'CryptoCurrency amount',
  `currency` enum('BTC','ETH','IOTA') NOT NULL COMMENT 'Currency',
  `value` bigint(20) DEFAULT NULL COMMENT 'The value of currency',
  `userid` bigint(20) NOT NULL COMMENT 'User Id of owner of assets',
  PRIMARY KEY (`walletid`),
  KEY `assetuserid_fk` (`userid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`walletid`, `label`, `amount`, `currency`, `value`, `userid`) VALUES
(1, 'USB driver', 100, 'BTC', 913066, 8),
(2, 'Binance', 100, 'ETH', 26719, 8),
(3, 'Binance', 150, 'ETH', 40082, 7);

-- --------------------------------------------------------

--
-- Table structure for table `tblsessions`
--

DROP TABLE IF EXISTS `tblsessions`;
CREATE TABLE IF NOT EXISTS `tblsessions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Session ID',
  `userid` bigint(20) NOT NULL COMMENT 'User ID',
  `accesstoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Access Token',
  `accesstokenexpiry` datetime NOT NULL COMMENT 'Access Token Expiry Date',
  `refreshtoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'refresh Token',
  `refreshtokenexpiry` datetime NOT NULL COMMENT 'Refresh Token Expiry',
  PRIMARY KEY (`id`),
  UNIQUE KEY `accesstoken` (`accesstoken`,`refreshtoken`),
  KEY `sessionuserid_fk` (`userid`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COMMENT='Sessions Table';

--
-- Dumping data for table `tblsessions`
--

INSERT INTO `tblsessions` (`id`, `userid`, `accesstoken`, `accesstokenexpiry`, `refreshtoken`, `refreshtokenexpiry`) VALUES
(9, 8, 'MDUyMzM2MjIyYzA4ZTVjNzIxODYzMDM1M2Q2ZWQ0ZjdjNDlmYmRkYTBjYzgzMTNiMTU2MDk1NDg1OQ==', '2019-06-19 17:54:19', 'ZDk5NmIxNzE3N2I0NzU4ZWYxZDI4NmU3MDJjNWM1OTgwODhhZWY0Y2I5ZmU3NjRjMTU2MDk1NDg1OQ==', '2019-07-03 17:34:19'),
(10, 7, 'YTdmYmEyNDcyMTM4ODNkNGM2ZjM2MDdiMjY2M2YyZDM4MjUyODUyOTIyM2E1ZjYzMTU2MDk1MTg1MA==', '2019-06-18 16:04:10', 'ODAwZDgwZGFiMTNiZDYxYWIxMmY1YTM4MzMyNTYzOGNiODVhNTQ4NDhhYjQwZTg0MTU2MDk1MTg1MA==', '2019-07-03 16:44:10');

-- --------------------------------------------------------

--
-- Table structure for table `tblusers`
--

DROP TABLE IF EXISTS `tblusers`;
CREATE TABLE IF NOT EXISTS `tblusers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'User ID',
  `fullname` varchar(255) NOT NULL COMMENT 'Users Full Name',
  `username` varchar(255) NOT NULL COMMENT 'Users Username',
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Users Password',
  `useractive` enum('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Is User Active',
  `loginattempts` int(1) NOT NULL DEFAULT '0' COMMENT 'Attempts To Log In',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COMMENT='Users Table';

--
-- Dumping data for table `tblusers`
--

INSERT INTO `tblusers` (`id`, `fullname`, `username`, `password`, `useractive`, `loginattempts`) VALUES
(6, 'Edgaras Avagyan', 'User One', '$2y$10$Et4BP31U7pSmGkYxpQONsO/5jJuaYcafOm7ZR/5xq..M.i6RBFFKy', 'Y', 0),
(7, 'Edgaras Avagyan 3', 'UserThree', '$2y$10$Pxxn7ckR.ReQJe.nKU2vjuJ.vz4p7NXbG998ZfDBr48AvPO5jArV.', 'Y', 0),
(8, 'Edgaras Avagyan 2', 'UserTwo', '$2y$10$/cc04p/HXIZFQsWcE7gj.u0VOWzhKzGvdoDc0PfTRJPY4Thv36Mg2', 'Y', 0);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assetuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);

--
-- Constraints for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD CONSTRAINT `sessionuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

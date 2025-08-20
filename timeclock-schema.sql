/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: timeclock
-- ------------------------------------------------------
-- Server version	10.11.13-MariaDB-0ubuntu0.24.04.1-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_logs` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `EmployeeID` int(11) DEFAULT NULL,
  `IP` varchar(45) DEFAULT NULL,
  `Timestamp` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `EmployeeID` (`EmployeeID`),
  CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `users` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pending_edits`
--

DROP TABLE IF EXISTS `pending_edits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_edits` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `EmployeeID` int(11) NOT NULL,
  `Date` date NOT NULL,
  `TimeIN` time DEFAULT NULL,
  `LunchStart` time DEFAULT NULL,
  `LunchEnd` time DEFAULT NULL,
  `TimeOut` time DEFAULT NULL,
  `Note` text DEFAULT NULL,
  `Reason` text NOT NULL,
  `Status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `SubmittedAt` datetime NOT NULL,
  `ReviewedAt` datetime DEFAULT NULL,
  `ReviewedBy` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `punch_changelog`
--

DROP TABLE IF EXISTS `punch_changelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `punch_changelog` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `EmployeeID` int(11) NOT NULL,
  `Date` date NOT NULL,
  `ChangedBy` varchar(100) DEFAULT NULL,
  `ChangeTime` datetime DEFAULT current_timestamp(),
  `FieldChanged` varchar(50) DEFAULT NULL,
  `OldValue` varchar(10) DEFAULT NULL,
  `NewValue` varchar(10) DEFAULT NULL,
  `Reason` text DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `SettingKey` varchar(50) NOT NULL,
  `SettingValue` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`SettingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timepunches`
--

DROP TABLE IF EXISTS `timepunches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `timepunches` (
  `EmployeeID` int(11) DEFAULT NULL,
  `Date` date DEFAULT NULL,
  `TimeIN` time DEFAULT NULL,
  `LatitudeIN` decimal(10,8) DEFAULT NULL,
  `LongitudeIN` decimal(11,8) DEFAULT NULL,
  `AccuracyIN` float DEFAULT NULL,
  `IPAddressIN` varchar(45) DEFAULT NULL,
  `LunchStart` time DEFAULT NULL,
  `LatitudeLunchStart` decimal(10,8) DEFAULT NULL,
  `LongitudeLunchStart` decimal(11,8) DEFAULT NULL,
  `AccuracyLunchStart` float DEFAULT NULL,
  `IPAddressLunchStart` varchar(45) DEFAULT NULL,
  `LunchEnd` time DEFAULT NULL,
  `LatitudeLunchEnd` decimal(10,8) DEFAULT NULL,
  `LongitudeLunchEnd` decimal(11,8) DEFAULT NULL,
  `AccuracyLunchEnd` float DEFAULT NULL,
  `IPAddressLunchEnd` varchar(45) DEFAULT NULL,
  `TimeOut` time DEFAULT NULL,
  `LatitudeOut` decimal(10,8) DEFAULT NULL,
  `LongitudeOut` decimal(11,8) DEFAULT NULL,
  `AccuracyOut` float DEFAULT NULL,
  `IPAddressOut` varchar(45) DEFAULT NULL,
  `TotalHours` decimal(5,2) DEFAULT NULL,
  `Note` text DEFAULT NULL,
  KEY `EmployeeID` (`EmployeeID`),
  CONSTRAINT `timepunches_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `users` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `LastName` varchar(255) NOT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `FirstName` varchar(255) NOT NULL,
  `Pass` varchar(255) DEFAULT NULL,
  `TagID` varchar(50) DEFAULT NULL,
  `ProfilePhoto` varchar(255) DEFAULT NULL,
  `ClockStatus` varchar(255) DEFAULT NULL,
  `Office` varchar(100) DEFAULT NULL,
  `TwoFASecret` varchar(255) DEFAULT NULL,
  `TwoFAEnabled` tinyint(1) DEFAULT 0,
  `RecoveryCodeHash` varchar(255) DEFAULT NULL,
  `AdminOverride2FA` tinyint(1) DEFAULT 1,
  `JobTitle` varchar(100) DEFAULT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `ThemePref` varchar(10) DEFAULT 'light',
  `TwoFARecoveryCode` text DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `TagID` (`TagID`)
) ENGINE=InnoDB AUTO_INCREMENT=1012 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-12  2:15:21

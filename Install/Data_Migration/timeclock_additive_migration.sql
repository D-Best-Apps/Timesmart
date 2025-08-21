-- timeclock_additive_migration_v2.sql
CREATE DATABASE IF NOT EXISTS `timeclock`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `timeclock`;

-- USERS
CREATE TABLE IF NOT EXISTS `users` (
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
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `LastName` varchar(255) NOT NULL,
  ADD COLUMN IF NOT EXISTS `Email` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `FirstName` varchar(255) NOT NULL,
  ADD COLUMN IF NOT EXISTS `Pass` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TagID` varchar(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ProfilePhoto` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ClockStatus` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Office` varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TwoFASecret` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TwoFAEnabled` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `RecoveryCodeHash` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `AdminOverride2FA` tinyint(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `JobTitle` varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `PhoneNumber` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ThemePref` varchar(10) DEFAULT 'light',
  ADD COLUMN IF NOT EXISTS `TwoFARecoveryCode` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LockOut` TINYINT(1) NOT NULL DEFAULT 0;

CREATE UNIQUE INDEX IF NOT EXISTS `uniq_users_tagid` ON `users`(`TagID`);

-- ADMINS
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `TwoFASecret` varchar(255) DEFAULT NULL,
  `TwoFAEnabled` tinyint(1) NOT NULL DEFAULT 0,
  `TwoFARecoveryCode` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`TwoFARecoveryCode`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `username` varchar(50) NOT NULL,
  ADD COLUMN IF NOT EXISTS `password` varchar(255) NOT NULL,
  ADD COLUMN IF NOT EXISTS `TwoFASecret` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TwoFAEnabled` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `TwoFARecoveryCode` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`TwoFARecoveryCode`));

CREATE UNIQUE INDEX IF NOT EXISTS `uniq_admins_username` ON `admins`(`username`);

-- Seed/refresh default admin
INSERT INTO `admins` (`username`,`password`)
VALUES ('admin', '$2a$10$bHLa.Sk0R08YNno3Y1cynu.tpk9ADCZitUuXkyXAqImWh.BC1KliG')
ON DUPLICATE KEY UPDATE `password`=VALUES(`password`);

-- SETTINGS
CREATE TABLE IF NOT EXISTS `settings` (
  `SettingKey` varchar(50) NOT NULL,
  `SettingValue` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`SettingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `settings`
  ADD COLUMN IF NOT EXISTS `SettingKey` varchar(50) NOT NULL,
  ADD COLUMN IF NOT EXISTS `SettingValue` varchar(255) DEFAULT NULL;

-- PENDING_EDITS
CREATE TABLE IF NOT EXISTS `pending_edits` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `pending_edits`
  ADD COLUMN IF NOT EXISTS `EmployeeID` int(11) NOT NULL,
  ADD COLUMN IF NOT EXISTS `Date` date NOT NULL,
  ADD COLUMN IF NOT EXISTS `TimeIN` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LunchStart` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LunchEnd` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TimeOut` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Note` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Reason` text NOT NULL,
  ADD COLUMN IF NOT EXISTS `Status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  ADD COLUMN IF NOT EXISTS `SubmittedAt` datetime NOT NULL,
  ADD COLUMN IF NOT EXISTS `ReviewedAt` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ReviewedBy` varchar(100) DEFAULT NULL;

-- PUNCH_CHANGELOG
CREATE TABLE IF NOT EXISTS `punch_changelog` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `punch_changelog`
  ADD COLUMN IF NOT EXISTS `EmployeeID` int(11) NOT NULL,
  ADD COLUMN IF NOT EXISTS `Date` date NOT NULL,
  ADD COLUMN IF NOT EXISTS `ChangedBy` varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ChangeTime` datetime DEFAULT current_timestamp(),
  ADD COLUMN IF NOT EXISTS `FieldChanged` varchar(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `OldValue` varchar(10) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `NewValue` varchar(10) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Reason` text DEFAULT NULL;

-- TIMEPUNCHES (no auto_increment here; add-only)
CREATE TABLE IF NOT EXISTS `timepunches` (
  `id` int(11) NOT NULL,
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
  `Note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `timepunches`
  ADD COLUMN IF NOT EXISTS `id` int(11) NULL,
  ADD COLUMN IF NOT EXISTS `EmployeeID` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Date` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TimeIN` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LatitudeIN` decimal(10,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LongitudeIN` decimal(11,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `AccuracyIN` float DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `IPAddressIN` varchar(45) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LunchStart` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LatitudeLunchStart` decimal(10,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LongitudeLunchStart` decimal(11,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `AccuracyLunchStart` float DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `IPAddressLunchStart` varchar(45) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LunchEnd` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LatitudeLunchEnd` decimal(10,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LongitudeLunchEnd` decimal(11,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `AccuracyLunchEnd` float DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `IPAddressLunchEnd` varchar(45) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TimeOut` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LatitudeOut` decimal(10,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `LongitudeOut` decimal(11,8) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `AccuracyOut` float DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `IPAddressOut` varchar(45) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `TotalHours` decimal(5,2) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Note` text DEFAULT NULL;

-- Helpful indexes
CREATE INDEX IF NOT EXISTS `idx_login_logs_employee` ON `login_logs`(`EmployeeID`);
CREATE INDEX IF NOT EXISTS `idx_timepunches_emp_date` ON `timepunches`(`EmployeeID`,`Date`);

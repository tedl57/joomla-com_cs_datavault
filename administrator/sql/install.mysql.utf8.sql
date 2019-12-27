CREATE TABLE IF NOT EXISTS `#__cs_report_data` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,

`datetimestamp` DATETIME NOT NULL ,
`report_type` TEXT NOT NULL ,
`report_data` TEXT NOT NULL ,
`ordering` INT(11)  NOT NULL ,
`state` TINYINT(1)  NOT NULL ,
`checked_out` INT(11)  NOT NULL ,
`checked_out_time` DATETIME NOT NULL ,
`created_by` INT(11)  NOT NULL ,
`modified_by` INT(11)  NOT NULL ,
PRIMARY KEY (`id`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;


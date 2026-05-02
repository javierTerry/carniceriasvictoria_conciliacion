TRUNCATE TABLE puritem;
TRUNCATE TABLE purhead;
TRUNCATE TABLE kdx;
UPDATE part set stock=0, stock01=0, stock02=0;


ALTER TABLE `kdx` ADD `store_id` INT(11) NOT NULL AFTER `created_at`;
DROP TABLE `p0000097`, `p0000098`, `p0000099`, `p0000100`, `p0000101`, `p0000102`, `p0000103`, `p0000104`, `p0000105`, `p0000106`, `p0000107`, `p0000108`, `p0000109`;
DELETE FROM `puritem` WHERE `puritem`.`id` = 1
ALTER TABLE `purhead` ADD `pieces` DECIMAL(15,3) NOT NULL AFTER `items`;

ALTER TABLE `trshead` DROP `mov`;

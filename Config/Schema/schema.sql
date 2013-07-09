DROP TABLE IF EXISTS `queued_tasks`;

CREATE TABLE IF NOT EXISTS `queued_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(255) NOT NULL,
  `data` text,
  `not_before` datetime NOT NULL,
  `fetched` datetime DEFAULT NULL,
  `completed` datetime DEFAULT NULL,
  `failed_count` int(10) unsigned NOT NULL,
  `failure_message` text,
  `worker_key` char(40) DEFAULT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `completed` (`completed`),
  KEY `not_before` (`not_before`),
  KEY `fetched` (`fetched`),
  KEY `worker_key` (`worker_key`),
  KEY `task` (`task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
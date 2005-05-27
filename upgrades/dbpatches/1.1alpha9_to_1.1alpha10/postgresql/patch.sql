--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
  id			serial,
  itemid                int4            DEFAULT '0' NOT NULL,
  clock                 int4            DEFAULT '0' NOT NULL,
  value                 varchar(255)    DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (itemid) REFERENCES items
);

CREATE INDEX history_log_i_c on history_str (itemid, clock);

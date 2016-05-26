-- Dunning management
-- Copyright (C) 2014 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.

CREATE TABLE llx_dunning
(
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity         INTEGER DEFAULT 1       NOT NULL, -- multicompany compatibility

	ref            VARCHAR(255), -- dunning reference

	datec          DATETIME, -- creation date
	dated          DATE, -- dunning date
	datep          DATE, -- dunning print date

	amount         DOUBLE(24, 8) DEFAULT 0 NOT NULL, -- dunnig amount

	fk_company     INTEGER                 NOT NULL, -- related company
	fk_user_author INTEGER,

	note_private   TEXT, -- private note
	note_public    TEXT, -- public note

	model_pdf      VARCHAR(255),

	mode_creation  VARCHAR(10) -- auto (by script)/ manuel
)
	ENGINE =innodb;

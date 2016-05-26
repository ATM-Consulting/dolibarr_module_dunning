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

ALTER TABLE llx_dunning ADD UNIQUE INDEX idx_dunning_uk_ref (ref, entity);

ALTER TABLE llx_dunning ADD INDEX idx_dunning_fk_company (fk_company);
ALTER TABLE llx_dunning ADD INDEX idx_dunning_fk_user_author (fk_user_author);

ALTER TABLE llx_dunning ADD CONSTRAINT fk_dunning_fk_company FOREIGN KEY (fk_company) REFERENCES llx_societe (rowid);
ALTER TABLE llx_dunning ADD CONSTRAINT fk_dunning_fk_user_author FOREIGN KEY (fk_user_author) REFERENCES llx_user (rowid);

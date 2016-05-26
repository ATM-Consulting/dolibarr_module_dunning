DELETE FROM llx_c_actioncomm WHERE code LIKE 'AC_DUNNING%';

INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, position) VALUES (1053011, 'AC_DUNNING_S', 'dunning', 'Send dunning by mail', 'dunning', 1, NULL, 10);

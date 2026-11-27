SELECT COUNT(*) AS has_table FROM information_schema.tables WHERE table_schema='project_o' AND table_name='Labor_Crews';
SELECT COUNT(*) AS crews FROM project_o.Labor_Crews;
SELECT crew_id, crew_name, leader_name, is_active FROM project_o.Labor_Crews ORDER BY crew_id LIMIT 12;
SELECT COUNT(*) AS users FROM project_o.Users;
SELECT user_id, username, display_name FROM project_o.Users LIMIT 12;

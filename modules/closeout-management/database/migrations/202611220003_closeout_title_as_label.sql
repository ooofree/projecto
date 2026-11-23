-- title becomes an optional custom label only. Legacy copies that just repeated
-- the current BOM material name are cleared, while real custom labels stay.
UPDATE project_closeout_items AS i
JOIN Materials AS m ON m.material_id = i.material_id AND m.project_id = i.project_id
SET i.title = ''
WHERE i.title = m.material_name;

UPDATE pr_editor_works 
SET status = 'pending_qc'
WHERE file_path IS NOT NULL 
  AND file_path != '' 
  AND status IN ('editing', 'draft', 'revised');

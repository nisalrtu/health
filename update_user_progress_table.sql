-- Add unique constraint to user_progress table to prevent duplicate lesson progress entries
USE lifestyle_medicine_app;

-- Add unique index for user_id and lesson_id combination
ALTER TABLE user_progress 
ADD UNIQUE KEY unique_user_lesson (user_id, lesson_id);

-- Display success message
SELECT 'User progress table updated successfully!' AS Status;

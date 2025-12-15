-- Insert default SMS templates for parts collection requests
INSERT INTO sms_templates (slug, name, content, is_active, created_at, updated_at)
VALUES
('parts_request_local', 'Parts Request (Local Market)', 'გამარჯობა, მიდმინარეობს თქვენი ავტომობილის აღსადგენად საჭირო დეტალების შეგროვება. სერვისთან დაკავშირებულ დეტალებს, უახლოეს მომავალში მიიღებთ.', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE content = VALUES(content), is_active = VALUES(is_active), updated_at = NOW();

INSERT INTO sms_templates (slug, name, content, is_active, created_at, updated_at)
VALUES
('parts_ordered', 'Parts Ordered', 'თქვენი ავტომობილისთვის საჭირო დეტალები შეკვეთილია. დამატებითი დეტალებისათვის ახლავე დაგიკავშირდებით.', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE content = VALUES(content), is_active = VALUES(is_active), updated_at = NOW();

-- Verify
SELECT slug, is_active FROM sms_templates WHERE slug IN ('parts_request_local','parts_ordered');

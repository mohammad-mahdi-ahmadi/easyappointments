-- Rich demo seed — "Aphrodite Beauty & Spa" (Limassol, Cyprus).
-- Loaded once, AFTER `provision-tenant provision demo-salon` has installed the schema
-- (console install seeds one admin + placeholder provider/customer/service, which this
-- replaces). Idempotent enough to re-run: it clears the demo rows it owns first.
-- Currency EUR; provider logins are intentionally NULL (bookable, not sign-in) — the only
-- login is the console admin (administrator / administrator).

SET NAMES utf8mb4;

-- --- Business identity -------------------------------------------------------
UPDATE `ea_settings` SET `value` = 'Aphrodite Beauty & Spa' WHERE `name` = 'company_name';
UPDATE `ea_settings` SET `value` = 'hello@aphrodite-spa.cy'  WHERE `name` = 'company_email';
UPDATE `ea_settings` SET `value` = 'https://demo-salon.cyprusinfo.dev' WHERE `name` = 'company_link';
UPDATE `ea_settings` SET `value` = '#c2185b' WHERE `name` = 'company_color';

-- --- Clear placeholder + prior demo rows (keep admin id=1) -------------------
DELETE FROM `ea_appointments`;
DELETE FROM `ea_secretaries_providers`;
DELETE FROM `ea_services_providers`;
DELETE FROM `ea_user_settings` WHERE `id_users` <> 1;
DELETE FROM `ea_users` WHERE `id_roles` IN (2, 3);
DELETE FROM `ea_services`;
DELETE FROM `ea_service_categories`;

-- --- Categories -------------------------------------------------------------
INSERT INTO `ea_service_categories` (`id`, `create_datetime`, `update_datetime`, `name`, `description`) VALUES
  (10, NOW(), NOW(), 'Hair',        'Cuts, colour and styling'),
  (20, NOW(), NOW(), 'Nails',       'Manicure and pedicure'),
  (30, NOW(), NOW(), 'Face & Body', 'Facials, massage and grooming');

-- --- Services (EUR) ---------------------------------------------------------
INSERT INTO `ea_services`
  (`id`, `create_datetime`, `update_datetime`, `name`, `duration`, `price`, `currency`, `description`, `slot_interval`, `color`, `attendants_number`, `is_private`, `id_service_categories`) VALUES
  (101, NOW(), NOW(), 'Haircut & Style',      45, 35.00, 'EUR', 'Wash, cut and blow-dry',        15, '#e57373', 1, 0, 10),
  (102, NOW(), NOW(), 'Hair Colour',         120, 90.00, 'EUR', 'Full colour with gloss',        15, '#f06292', 1, 0, 10),
  (103, NOW(), NOW(), 'Blow Dry',             30, 25.00, 'EUR', 'Shampoo and blow-dry',          15, '#ba68c8', 1, 0, 10),
  (104, NOW(), NOW(), 'Classic Manicure',     40, 25.00, 'EUR', 'File, shape and polish',        10, '#9575cd', 1, 0, 20),
  (105, NOW(), NOW(), 'Gel Manicure',         60, 40.00, 'EUR', 'Long-lasting gel finish',       15, '#7986cb', 1, 0, 20),
  (106, NOW(), NOW(), 'Deluxe Pedicure',      50, 35.00, 'EUR', 'Soak, scrub and polish',        10, '#64b5f6', 1, 0, 20),
  (107, NOW(), NOW(), 'Signature Facial',     60, 55.00, 'EUR', 'Deep-cleanse facial treatment', 15, '#4db6ac', 1, 0, 30),
  (108, NOW(), NOW(), 'Deep Tissue Massage',  60, 65.00, 'EUR', 'Therapeutic full-body massage', 15, '#81c784', 1, 0, 30),
  (109, NOW(), NOW(), 'Eyebrow Shaping',      20, 15.00, 'EUR', 'Shape and tidy',                10, '#aed581', 1, 0, 30);

-- --- Providers (role 2). username/password NULL => bookable, not sign-in. ---
INSERT INTO `ea_users`
  (`id`, `create_datetime`, `update_datetime`, `first_name`, `last_name`, `email`, `mobile_number`, `timezone`, `language`, `is_private`, `id_roles`) VALUES
  (11, NOW(), NOW(), 'Elena',  'Georgiou',    'elena@aphrodite-spa.cy',  '+35799000011', 'Asia/Nicosia', 'english', 0, 2),
  (12, NOW(), NOW(), 'Maria',  'Constantinou','maria@aphrodite-spa.cy',  '+35799000012', 'Asia/Nicosia', 'english', 0, 2),
  (13, NOW(), NOW(), 'Sophia', 'Andreou',     'sophia@aphrodite-spa.cy', '+35799000013', 'Asia/Nicosia', 'english', 0, 2);

INSERT INTO `ea_user_settings` (`id_users`, `username`, `password`, `salt`, `working_plan`, `notifications`, `google_sync`, `caldav_sync`, `calendar_view`) VALUES
  (11, NULL, NULL, NULL, '{"monday":{"start":"09:00","end":"18:00","breaks":[{"start":"13:00","end":"14:00"}]},"tuesday":{"start":"09:00","end":"18:00","breaks":[{"start":"13:00","end":"14:00"}]},"wednesday":{"start":"09:00","end":"18:00","breaks":[{"start":"13:00","end":"14:00"}]},"thursday":{"start":"09:00","end":"18:00","breaks":[{"start":"13:00","end":"14:00"}]},"friday":{"start":"09:00","end":"18:00","breaks":[{"start":"13:00","end":"14:00"}]},"saturday":{"start":"09:00","end":"15:00","breaks":[]},"sunday":null}', 1, 0, 0, 'default'),
  (12, NULL, NULL, NULL, '{"monday":{"start":"10:00","end":"19:00","breaks":[{"start":"14:00","end":"15:00"}]},"tuesday":{"start":"10:00","end":"19:00","breaks":[{"start":"14:00","end":"15:00"}]},"wednesday":{"start":"10:00","end":"19:00","breaks":[{"start":"14:00","end":"15:00"}]},"thursday":{"start":"10:00","end":"19:00","breaks":[{"start":"14:00","end":"15:00"}]},"friday":{"start":"10:00","end":"19:00","breaks":[{"start":"14:00","end":"15:00"}]},"saturday":{"start":"10:00","end":"16:00","breaks":[]},"sunday":null}', 1, 0, 0, 'default'),
  (13, NULL, NULL, NULL, '{"monday":null,"tuesday":{"start":"09:00","end":"17:00","breaks":[{"start":"12:30","end":"13:30"}]},"wednesday":{"start":"09:00","end":"17:00","breaks":[{"start":"12:30","end":"13:30"}]},"thursday":{"start":"09:00","end":"17:00","breaks":[{"start":"12:30","end":"13:30"}]},"friday":{"start":"09:00","end":"17:00","breaks":[{"start":"12:30","end":"13:30"}]},"saturday":{"start":"09:00","end":"14:00","breaks":[]},"sunday":null}', 1, 0, 0, 'default');

-- --- Provider <-> service links ---------------------------------------------
INSERT INTO `ea_services_providers` (`id_users`, `id_services`) VALUES
  (11, 101), (11, 102), (11, 103),          -- Elena: hair
  (12, 104), (12, 105), (12, 106),          -- Maria: nails
  (13, 107), (13, 108), (13, 109);          -- Sophia: face & body

-- --- Customers (role 3) -----------------------------------------------------
INSERT INTO `ea_users`
  (`id`, `create_datetime`, `update_datetime`, `first_name`, `last_name`, `email`, `mobile_number`, `timezone`, `language`, `is_private`, `id_roles`) VALUES
  (21, NOW(), NOW(), 'Andreas',   'Ioannou',    'andreas@example.cy',   '+35799100021', 'Asia/Nicosia', 'english', 0, 3),
  (22, NOW(), NOW(), 'Christina', 'Pavlou',     'christina@example.cy', '+35799100022', 'Asia/Nicosia', 'english', 0, 3),
  (23, NOW(), NOW(), 'Nikos',     'Charalambous','nikos@example.cy',    '+35799100023', 'Asia/Nicosia', 'english', 0, 3),
  (24, NOW(), NOW(), 'Eleni',     'Demetriou',  'eleni@example.cy',     '+35799100024', 'Asia/Nicosia', 'english', 0, 3);

-- --- A few upcoming appointments --------------------------------------------
INSERT INTO `ea_appointments`
  (`create_datetime`, `update_datetime`, `book_datetime`, `start_datetime`, `end_datetime`, `notes`, `hash`, `status`, `is_unavailability`, `id_users_provider`, `id_users_customer`, `id_services`) VALUES
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 10:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 10:45:00'), '',       MD5('salon-appt-1'), 'Booked',    0, 11, 21, 101),
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 11:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 12:00:00'), '',       MD5('salon-appt-2'), 'Confirmed', 0, 12, 22, 105),
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 3 DAY), ' 14:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 3 DAY), ' 15:00:00'), 'Regular', MD5('salon-appt-3'), 'Booked',    0, 13, 23, 107),
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 5 DAY), ' 16:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 5 DAY), ' 16:30:00'), '',       MD5('salon-appt-4'), 'Booked',    0, 11, 24, 103);

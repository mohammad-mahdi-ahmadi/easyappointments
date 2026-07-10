-- Rich demo seed — "Nicosia Family Clinic" (Nicosia, Cyprus).
-- Loaded once, AFTER `provision-tenant provision demo-clinic` has installed the schema.
-- A deliberately DIFFERENT business (healthcare) from demo-salon, to demonstrate two isolated
-- tenants on one runtime. Currency EUR; provider logins NULL (bookable, not sign-in); the only
-- login is the console admin (administrator / administrator).

SET NAMES utf8mb4;

-- --- Business identity -------------------------------------------------------
UPDATE `ea_settings` SET `value` = 'Nicosia Family Clinic' WHERE `name` = 'company_name';
UPDATE `ea_settings` SET `value` = 'reception@nicosia-clinic.cy' WHERE `name` = 'company_email';
UPDATE `ea_settings` SET `value` = 'https://demo-clinic.cyprusinfo.dev' WHERE `name` = 'company_link';
UPDATE `ea_settings` SET `value` = '#1565c0' WHERE `name` = 'company_color';

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
  (10, NOW(), NOW(), 'General Medicine', 'GP consultations and check-ups'),
  (20, NOW(), NOW(), 'Dental',           'Dental care and hygiene'),
  (30, NOW(), NOW(), 'Physiotherapy',    'Assessment and rehabilitation');

-- --- Services (EUR) ---------------------------------------------------------
INSERT INTO `ea_services`
  (`id`, `create_datetime`, `update_datetime`, `name`, `duration`, `price`, `currency`, `description`, `slot_interval`, `color`, `attendants_number`, `is_private`, `id_service_categories`) VALUES
  (101, NOW(), NOW(), 'GP Consultation',    20, 40.00, 'EUR', 'General practitioner visit',   10, '#4dd0e1', 1, 0, 10),
  (102, NOW(), NOW(), 'Health Check-up',    45, 80.00, 'EUR', 'Comprehensive health review', 15, '#4fc3f7', 1, 0, 10),
  (103, NOW(), NOW(), 'Vaccination',        15, 25.00, 'EUR', 'Single vaccination visit',    15, '#4db6ac', 1, 0, 10),
  (104, NOW(), NOW(), 'Dental Check-up',    30, 50.00, 'EUR', 'Examination and advice',      15, '#7986cb', 1, 0, 20),
  (105, NOW(), NOW(), 'Teeth Cleaning',     45, 70.00, 'EUR', 'Scale and polish',            15, '#9575cd', 1, 0, 20),
  (106, NOW(), NOW(), 'Dental Filling',     40, 90.00, 'EUR', 'Single-surface filling',      10, '#ba68c8', 1, 0, 20),
  (107, NOW(), NOW(), 'Physio Assessment',  45, 60.00, 'EUR', 'Initial physiotherapy review',15, '#81c784', 1, 0, 30),
  (108, NOW(), NOW(), 'Physio Session',     30, 45.00, 'EUR', 'Follow-up treatment session', 10, '#aed581', 1, 0, 30),
  (109, NOW(), NOW(), 'Sports Massage',     60, 70.00, 'EUR', 'Deep-tissue sports massage',  15, '#dce775', 1, 0, 30);

-- --- Providers (role 2). username/password NULL => bookable, not sign-in. ---
INSERT INTO `ea_users`
  (`id`, `create_datetime`, `update_datetime`, `first_name`, `last_name`, `email`, `mobile_number`, `timezone`, `language`, `is_private`, `id_roles`) VALUES
  (11, NOW(), NOW(), 'Andreas',  'Michael',   'dr.michael@nicosia-clinic.cy',   '+35722000011', 'Asia/Nicosia', 'english', 0, 2),
  (12, NOW(), NOW(), 'Christina','Nikolaou',  'dr.nikolaou@nicosia-clinic.cy',  '+35722000012', 'Asia/Nicosia', 'english', 0, 2),
  (13, NOW(), NOW(), 'Yiannis',  'Petrou',    'y.petrou@nicosia-clinic.cy',     '+35722000013', 'Asia/Nicosia', 'english', 0, 2);

INSERT INTO `ea_user_settings` (`id_users`, `username`, `password`, `salt`, `working_plan`, `notifications`, `google_sync`, `caldav_sync`, `calendar_view`) VALUES
  (11, NULL, NULL, NULL, '{"monday":{"start":"08:00","end":"16:00","breaks":[{"start":"12:00","end":"12:30"}]},"tuesday":{"start":"08:00","end":"16:00","breaks":[{"start":"12:00","end":"12:30"}]},"wednesday":{"start":"08:00","end":"16:00","breaks":[{"start":"12:00","end":"12:30"}]},"thursday":{"start":"08:00","end":"16:00","breaks":[{"start":"12:00","end":"12:30"}]},"friday":{"start":"08:00","end":"14:00","breaks":[]},"saturday":null,"sunday":null}', 1, 0, 0, 'default'),
  (12, NULL, NULL, NULL, '{"monday":{"start":"09:00","end":"17:00","breaks":[{"start":"13:00","end":"14:00"}]},"tuesday":{"start":"09:00","end":"17:00","breaks":[{"start":"13:00","end":"14:00"}]},"wednesday":null,"thursday":{"start":"09:00","end":"17:00","breaks":[{"start":"13:00","end":"14:00"}]},"friday":{"start":"09:00","end":"17:00","breaks":[{"start":"13:00","end":"14:00"}]},"saturday":{"start":"09:00","end":"13:00","breaks":[]},"sunday":null}', 1, 0, 0, 'default'),
  (13, NULL, NULL, NULL, '{"monday":{"start":"10:00","end":"18:00","breaks":[{"start":"14:00","end":"14:30"}]},"tuesday":{"start":"10:00","end":"18:00","breaks":[{"start":"14:00","end":"14:30"}]},"wednesday":{"start":"10:00","end":"18:00","breaks":[{"start":"14:00","end":"14:30"}]},"thursday":{"start":"10:00","end":"18:00","breaks":[{"start":"14:00","end":"14:30"}]},"friday":{"start":"10:00","end":"18:00","breaks":[{"start":"14:00","end":"14:30"}]},"saturday":null,"sunday":null}', 1, 0, 0, 'default');

-- --- Provider <-> service links ---------------------------------------------
INSERT INTO `ea_services_providers` (`id_users`, `id_services`) VALUES
  (11, 101), (11, 102), (11, 103),          -- Dr. Michael: general medicine
  (12, 104), (12, 105), (12, 106),          -- Dr. Nikolaou: dental
  (13, 107), (13, 108), (13, 109);          -- Petrou: physiotherapy

-- --- Customers (role 3) -----------------------------------------------------
INSERT INTO `ea_users`
  (`id`, `create_datetime`, `update_datetime`, `first_name`, `last_name`, `email`, `mobile_number`, `timezone`, `language`, `is_private`, `id_roles`) VALUES
  (21, NOW(), NOW(), 'Marios',   'Georgiades', 'marios@example.cy',  '+35799200021', 'Asia/Nicosia', 'english', 0, 3),
  (22, NOW(), NOW(), 'Anna',     'Kyriacou',   'anna@example.cy',    '+35799200022', 'Asia/Nicosia', 'english', 0, 3),
  (23, NOW(), NOW(), 'Panayiotis','Savva',     'panayiotis@example.cy','+35799200023','Asia/Nicosia', 'english', 0, 3),
  (24, NOW(), NOW(), 'Maria',    'Loizou',     'maria.l@example.cy', '+35799200024', 'Asia/Nicosia', 'english', 0, 3);

-- --- A few upcoming appointments --------------------------------------------
INSERT INTO `ea_appointments`
  (`create_datetime`, `update_datetime`, `book_datetime`, `start_datetime`, `end_datetime`, `notes`, `hash`, `status`, `is_unavailability`, `id_users_provider`, `id_users_customer`, `id_services`) VALUES
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 09:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 09:20:00'), '',         MD5('clinic-appt-1'), 'Booked',    0, 11, 21, 101),
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 10:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 10:45:00'), 'Annual',   MD5('clinic-appt-2'), 'Confirmed', 0, 12, 22, 105),
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 4 DAY), ' 11:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 4 DAY), ' 11:45:00'), '',         MD5('clinic-appt-3'), 'Booked',    0, 13, 23, 107),
  (NOW(), NOW(), NOW(), CONCAT(DATE_ADD(CURDATE(), INTERVAL 6 DAY), ' 13:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 6 DAY), ' 13:15:00'), '',         MD5('clinic-appt-4'), 'Booked',    0, 11, 24, 103);

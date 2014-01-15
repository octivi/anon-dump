-- truncate users orders - devs should not see this
TRUNCATE user_orders;

-- anonymize users personal data
UPDATE users
SET firstName = md5(firstName),
	lastName = md5(lastName),
	address = md5(address),
	email = CONCAT('testing+', username, '@email.com');

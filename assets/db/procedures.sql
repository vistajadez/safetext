-- --------------------------------------------------------------------------------
-- Generate Token
-- Generates a device auth token for a user
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE PROCEDURE `generateToken` (IN usernameIn VARCHAR(24), IN passIn VARCHAR(24), IN deviceSig VARCHAR(64), IN deviceDesc VARCHAR(64))
BEGIN
	/* get user ID */
	SET @userId = (SELECT `id` FROM users WHERE `username` = usernameIn AND `pass` = passIn);
        SET @msg = NULL;
	
	IF @userId IS NOT NULL THEN
		/* see if this device exists */
		SET @deviceId = (SELECT `id` FROM sync_device WHERE `user_id` = @userId AND `signature` = deviceSig);
		IF @deviceId IS NOT NULL THEN
			/* get the existing token */
			SET @token = (SELECT `token` FROM sync_device WHERE `id` = @deviceId);

			/* if token is empty, meaning it has been previously expired or cleared, generate a new one */
			if @token < 1 THEN
				SET @token = CAST(MD5(CONCAT(CONCAT('SafeText-hashsalt', usernameIn), UNIX_TIMESTAMP())) AS CHAR);
				UPDATE sync_device SET `token`=@token, `token_expires`=DATE_ADD(CURDATE(),INTERVAL 5 DAY), `description`=deviceDesc WHERE `user_id` = @userId AND `id` = @deviceId; 
			ELSE
				/* reset the init flag */
				UPDATE sync_device SET `is_initialized`=0, `description`=deviceDesc WHERE `user_id` = @userId AND `id` = @deviceId;
				/* clear the sync queue */
				DELETE FROM sync_queue WHERE `user_id` = @userId AND `device_id` = @deviceId;
			END IF;
			
		ELSE
			/* make sure that there aren’t too many devices already registered */
			SET @numDevices = (SELECT COUNT(*) FROM `sync_device` WHERE user_id=@userId);

			IF @numDevices < 2 THEN
				/* create new device entry */
				SET @tokenString = CAST(MD5(CONCAT(CONCAT('SafeText-hashsalt', usernameIn), UNIX_TIMESTAMP())) AS CHAR);
				INSERT INTO sync_device (id,user_id,signature,description,is_initialized,token,token_expires) VALUES('', @userId, deviceSig, deviceDesc, '0', @tokenString, DATE_ADD(CURDATE(),INTERVAL 5 DAY));
				IF LAST_INSERT_ID() IS NOT NULL THEN
					SET @token = @tokenString;
				ELSE
					SET @userId = 0;
					SET @token = NULL;
					SET @msg = "Unable to create new token";
				END IF;
			ELSE
				SET @userId = 0;
				SET @msg = "Too many devices registered. Unregister unused devices using the web client Settings page";
			END IF;
		END IF;

	ELSE
		/* user/pass didn't match */
		SET @userId = 0;
        SET @token = NULL;
		SET @msg = "No match found for that username and password";
    END IF;

	SELECT @userId AS id, @token AS token, @msg AS msg;

END $$


-- --------------------------------------------------------------------------------
-- Expire Token
-- Clears a user's auth token.
-- --------------------------------------------------------------------------------
DELIMITER $$

CREATE DEFINER=`maxdistrodb`@`%.%.%.%` PROCEDURE `expireToken`(IN tokenIn VARCHAR(64))
BEGIN
	UPDATE sync_device SET `token`='', `token_expires`= CURDATE() WHERE `token` = tokenIn LIMIT 1; 
END
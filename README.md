safetext
========

### SafeText Web Client

PHP Engine for driving the web client and server components of an encrypted safe text messaging mobile app (native mobile app code is not included in this project). The mobile app will send all text messages via HTTPS to the server using a RESTful API. The server will then assign the message to the appropriate user's device queue. Messages are removed from the server after a specified expiration time. Messages, contacts, and user settings will be exchanged by mobile app and server via a simple sync protocol over the RESTful API.

> _NOTE: config.php, normally located at the framework root level, is not included in this repository for copyright and security purposes. To see a sample config file, check out the SquareOne repository (http://github.com/deztopia/squareone)_

**_Project Requirement Specifications for Mobile App:_**
  1. Application will run in the background for 24 hours with no activity after user chooses ‘Stand-By’ option, during this time the application will check the database for new messages once every 1 to 15 minutes minutes (user configurable) if number of messages is greater than zero it will then notify the user of a new message with a ‘safe-text shield’ in the notification bar. 
  2. When user completely logs out it will not check for messages until the user logs back in
  3. When user is actively using application it will check for new messages every 10 seconds and will notify when new messages is greater than zero
  4. No message is to be saved to hard drive, it will all just exist in RAM
  5. We will only be displaying text only now, no photo or video messages in this version
  6. Initial login:
      - Insert Username & Password
      - App in stand by Username is grayed out only insert password
      - If password is incorrect user has to enter Username & Password
  7. Quick Uninstall
      - Removes application from the phone
      - All messages and other data is not removed from database
  8. Once Authenticated
      -  Read, send and delete messages
      -  Update address book
  9. Insert/update/delete
  10. Update account settings
      - Change username
      - Change password
      - Change/remove email address
      - Forgot Password: Send temporary password to email if provided, if no email on file password cannot be reset
      - If Temporary password is used all messages and contacts are deleted
  11. Logout/standby
  12. Link to Mobile Website (opens mobile browser)

**_Project Requirement Specifications for Web Client:_**
  1. All communication for Website and Mobile Application will be using SSL
  2. All database queries will be stored procedures
  3. Account sign up is via website only
  4. Consolidation of configuration variables for application config
  5. Installation of Google Analytics to track website traffic statistics
  6. Create Account
      - Create unused username
      - Set password
      - Email address optional (if not entered then no way to recover lost password)
  7. Login
      - Read, send and delete messages
      - Update address book
  8. Insert/update/delete
  9. Update account settings
      - Change username
      - Change password
      - Change/remove email address
      - Payment Processing. User subscription membership with support for monthly or annual payments.
      - Forgot Password: Send temporary password to email if provided, if no email on file password cannot be reset
      - If Temporary password is used all messages and contacts are deleted
  10. Messages auto-expire. Default is 24 hours; sender can opt for a shorter timespan.
  
**_Subscription Options_**
- $5 monthly
- $15 for six months
- $25 for one year (promo $20)
- $35 for two years (promo $30)

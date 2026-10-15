/*
 * config.js -- the only file you normally edit to connect the form to a backend.
 *
 * API_URL   : the PHP registration endpoint (php-admin/api/register.php).
 *             Start the PHP server on port 8080 and the default below works.
 * DEMO_MODE : true  = nothing is sent anywhere. The form validates and then shows a success
 *                     message, so the front end can be demonstrated on its own.
 *             false = the form really posts to API_URL and the record is saved in MySQL.
 */
window.PORTAL_CONFIG = {
  API_URL: 'http://localhost:8080/api/register.php',
  DEMO_MODE: false
};

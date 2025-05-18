# Navicat HTTP Tunnel for PHP 8.1+

This project provides a secure and customizable HTTP tunnel script to enable remote MySQL access via Navicat through HTTP/HTTPS, especially useful when direct MySQL connections are restricted by firewalls or hosting environments.

---

## 🚀 Features

- ✅ Compatible with Navicat MySQL tunneling
- ✅ Fully PHP 8.1+ compatible (uses `mysqli`)
- ✅ IP whitelisting via JSON file
- ✅ Optional debug logging to file
- ✅ Optional error logging to file
- ✅ Clean, structured code with inline documentation
- ✅ Fully customizable configuration block

---

## 🧱 Requirements

- PHP 8.1 or newer
- MySQL server accessible by the PHP environment
- Web server (Apache, Nginx, etc.) with access to run PHP
- Navicat (Premium or MySQL edition)

---

## 📦 Files

| File                      | Purpose                                      |
|---------------------------|----------------------------------------------|
| `tunnel.php`              | Main HTTP tunnel script                      |
| `allowedIps.json`         | JSON list of allowed IP addresses            |
| `navicat_tunnel_debug.log`| Debug log (created automatically)            |
| `navicat_tunnel_error.log`| PHP error log (if enabled)                   |

---

## ⚙️ Configuration

All settings are located at the top of `tunnel.php`:

```php
$config = [
    'enable_error_log' => true,
    'enable_debug_log' => true,
    'ip_filtering' => false,
    'error_log_file' => __DIR__ . '/navicat_tunnel_error.log',
    'debug_log_file' => __DIR__ . '/navicat_tunnel_debug.log',
    'allowed_ips_file' => __DIR__ . '/allowedIps.json',
    'query_log_max_length' => 300,
];
```
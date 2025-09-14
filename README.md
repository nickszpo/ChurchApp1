# St. Thomas Aquinas Parish Church Event and Resource Management System

A comprehensive event and resource management system for St. Thomas Aquinas Parish Church.

## 🌟 Features

- 📅 Event scheduling and management
- 🏗️ Resource allocation and tracking
- 🔐 User authentication and authorization
- 📢 Announcement system
- 🗓️ Appointment management
- 👥 User management
- 📱 Responsive design

## 🚀 Quick Start

### Prerequisites

- PHP 8.0 or higher
- SQLite3
- Web server (Apache/Nginx) with PHP support
- Composer (for dependency management)

### Local Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/ChurchApp1.git
   cd ChurchApp1
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up the database**
   - The application uses SQLite by default
   - Ensure the `database` directory is writable:
     ```bash
     chmod -R 755 database/
     ```

4. **Initialize the database**
   ```bash
   php init_db.php
   ```

5. **Configure your web server**
   - Point your web server to the project root directory
   - For Apache, ensure `mod_rewrite` is enabled

6. **Access the application**
   - Open your browser and navigate to: `http://localhost/ChurchApp1`
   - Default admin credentials:
     - Username: `admin`
     - Password: `admin123`

## 🔧 Configuration

### Environment Variables
Create a `.env` file in the root directory with the following content:

```
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/ChurchApp1

DB_CONNECTION=sqlite
DB_DATABASE=/path/to/your/database/st_thomas_aquinas_parish_events.db
```

### Database Configuration
Edit `config/database.php` if you need to change database settings.

## 🚀 Deployment

### Shared Hosting
1. Upload all files to your web server
2. Set the document root to the `public` directory
3. Make sure the `database` directory is writable
4. Update the database configuration in `config/database.php`

### Docker (Coming Soon)
```bash
docker-compose up -d
```

## 🔒 Security

- Change the default admin password after first login
- Keep your `.env` file secure and never commit it to version control
- Regularly update your dependencies
- Use HTTPS in production

## 🤝 Contributing

1. Fork the repository
2. Create a new branch for your feature
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built with PHP, SQLite, and Bootstrap 5
- Icons by [Bootstrap Icons](https://icons.getbootstrap.com/)
- Thanks to all contributors

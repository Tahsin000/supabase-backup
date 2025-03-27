# Supabase Database Backup

## Overview

This Laravel command allows you to backup your Supabase database (PostgreSQL) and automatically email the backup file as an attachment. The backup includes the table structure (`CREATE TABLE`) and data (`INSERT INTO`) in `.sql` format. The email is sent with a modern HTML template and contains details of the backup.

## Features
- Backup all Supabase database tables to a `.sql` file.
- Generate SQL commands to create the table structure and insert the data.
- Send the backup file as an email attachment.
- Log progress, errors, and details of the backup process.

## Installation

### Prerequisites

Ensure the following prerequisites are met before proceeding with the installation:
- **PHP >= 7.4**
- **Laravel 8.x or higher**
- A working **Supabase** database instance.
- An **SMTP mail configuration** for sending the backup email.

### Steps to Install

1. **Clone the Repository**

   Clone the repository from GitHub to your local machine or server.

   ```bash
   git clone https://github.com/Tahsin000/supabase-backup.git
   cd supabase-backup
# supabase-backup

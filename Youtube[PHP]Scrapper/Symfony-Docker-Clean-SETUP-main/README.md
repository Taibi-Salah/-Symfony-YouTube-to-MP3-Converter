# Symfony YouTube to MP3 Converter

This project is a Symfony web application that allows users to convert YouTube videos to MP3 files. The application limits free conversions to 5 per user and prompts users to subscribe to a premium service for unlimited conversions.

# Symfony YouTube to MP3 Converter

This project is a Symfony web application that allows users to convert YouTube videos to MP3 files. The application limits free conversions to 5 per user and prompts users to subscribe to a premium service for unlimited conversions.

## Prerequisites

Before you begin, ensure you have the following installed on your machine:

- Docker
- Docker Compose

## Getting Started

Follow these steps to set up and run the application:

### 1. Clone the Repository

Clone this repository to your local machine:


### 2. Run the Setup Script

Run the provided shell script to build and start the Docker containers and install the necessary bundles:


### 3. Access the Application

Once the setup script completes, you can access the application in your web browser at:



## Project Structure

- `app/`: Contains the Symfony application code.
  - `src/Controller/HomeController.php`: The main controller handling the YouTube to MP3 conversion logic.
  - `templates/home/index.html.twig`: The Twig template for the home page.
- `public/`: The web server's document root.
  - `converted_files/`: Directory where converted MP3 files are stored.
- `docker-compose.yml`: Docker Compose configuration file.
- `setup.sh`: Shell script to build and start the Docker containers and install necessary bundles.

## Usage

### Converting a YouTube Video to MP3

1. Open the application in your web browser.
2. Enter the YouTube URL in the provided input field.
3. Click the "Convert" button.
4. If the conversion is successful, a download link for the MP3 file will be provided.

### Conversion Limit

- Each user is allowed up to 5 free conversions.
- After reaching the limit, users will be prompted to subscribe to a premium service for unlimited conversions.

## Development

### Running the Application Locally

To run the application locally without Docker, follow these steps:

1. Install PHP and Composer.
2. Install Symfony CLI.
3. Install the project dependencies:

   ```bash
   composer install
   ```

4. Start the Symfony server:

   ```bash
   symfony server:start
   ```

5. Access the application in your web browser at:

   ```
   http://localhost:8000
   ```

### Testing

To run the tests, use the following command:

## Contributing

If you would like to contribute to this project, please fork the repository and submit a pull request.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
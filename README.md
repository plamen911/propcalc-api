# Estate Calculator API

This is the API module for the Estate Calculator application. It provides the backend services for the admin and client modules.

## Key Features

- Real estate insurance policy calculation and management
- Property data handling (settlements, estate types, distance to water)
- Insurance tariff calculations with preset and custom options
- Policy document generation with unique codes
- User authentication and authorization
- Email notifications for policy confirmations
- Comprehensive validation for all insurance parameters

## Technical Stack

- Symfony 6.x framework
- Doctrine ORM for database interactions
- JWT authentication for secure API access
- RESTful API design principles
- Comprehensive validation and error handling

## API Endpoints

### Settlements Autocomplete

```
GET /api/v1/settlements
```

**Parameters:**
- `query` (string, optional): The search term to match against settlement names or postal codes
- `limit` (integer, optional, default: 10): Maximum number of results to return

**Response:**
The endpoint returns a JSON array of settlement objects, each containing:
- `id`: The settlement ID
- `name`: The settlement name
- `postCode`: The settlement postal code

### Create Insurance Policy

```
POST /api/v1/insurance-policies
```

**Request Body:**
The request body should be a JSON object with the following properties:
- `settlement_id` (integer, required): The ID of the settlement
- `estate_type_id` (integer, required): The ID of the estate type
- `estate_subtype_id` (integer, required): The ID of the estate subtype
- `distance_to_water_id` (integer, required): The ID of the distance to water
- `area_sq_meters` (number, required): The area in square meters (must be between 0 and 100000)

**Response:**
The endpoint returns a JSON object representing the created insurance policy, containing:
- `id`: The insurance policy ID
- `settlement_id`: The settlement ID
- `estate_type_id`: The estate type ID
- `estate_subtype_id`: The estate subtype ID
- `distance_to_water_id`: The distance to water ID
- `area_sq_meters`: The area in square meters
- `subtotal`: The subtotal amount
- `discount`: The discount amount
- `subtotal_tax`: The subtotal tax amount
- `total`: The total amount
- `created_at`: The timestamp when the policy was created
- `updated_at`: The timestamp when the policy was last updated

## Authentication

The Estate Calculator API supports multiple authentication methods:

### JWT Authentication

JWT (JSON Web Token) authentication is the primary method used for securing the API. To authenticate:

1. Obtain a JWT token by calling the anonymous authentication endpoint:
   ```
   POST /api/v1/auth/anonymous
   ```

2. Include the token in subsequent requests using the Authorization header:
   ```
   Authorization: Bearer <your_jwt_token>
   ```

JWT tokens have a limited lifespan and will need to be refreshed periodically.

### API Key Authentication (Optional)

For server-to-server communication, API key authentication can be implemented. This method allows for stateless authentication without the need for token refresh.

To use API key authentication:

1. Include the API key in the request header:
   ```
   X-API-KEY: <your_api_key>
   ```

2. Alternatively, you can include it as a query parameter:
   ```
   GET /api/v1/endpoint?api_key=<your_api_key>
   ```

For implementation details, see the example in [API_KEY_IMPLEMENTATION_EXAMPLE.php](API_KEY_IMPLEMENTATION_EXAMPLE.php).

## Setup Instructions

1. Install dependencies:
   ```bash
   composer install
   ```

2. Set up the database:
   ```bash
   # For MySQL: Create the database if it doesn't exist
   php bin/console doctrine:database:create --if-not-exists

   # For MySQL: Drop the database if you need to start fresh
   # php bin/console doctrine:database:drop --force

   # Run migrations to set up the database schema
   php bin/console doctrine:migrations:migrate
   ```

3. Seed the database:

   You can seed all database tables at once using the following command:
   ```bash
   php bin/console app:seed-all
   ```

   Or you can seed individual tables using the following commands:
   ```bash
   php bin/console app:seed-insurance-policy-configs
   php bin/console app:seed-estate-types
   php bin/console app:seed-settlements
   php bin/console app:seed-distance-to-water
   php bin/console app:seed-insurance-clauses
   php bin/console app:seed-tariff-presets
   php bin/console app:seed-tariff-preset-clauses
   ```

   Note: The order of commands is important when seeding individual tables to respect foreign key constraints.

4. Generate JWT Keys for Authentication:
   ```bash
   # Make the script executable
   chmod +x generate_jwt_keys.sh

   # Run the script to generate the keys
   ./generate_jwt_keys.sh

   # Verify the keys were created
   ls -la config/jwt
   ```

   This will create the necessary JWT keys for token-based authentication. For more details, see [JWT_KEYS_SETUP.md](JWT_KEYS_SETUP.md).

5. Set up APP_SECRET:

   Generate a secure APP_SECRET using one of these methods:
   ```bash
   # Using OpenSSL
   openssl rand -hex 32

   # Using Symfony console
   php bin/console secrets:generate-keys

   # Using PHP
   php -r "echo bin2hex(random_bytes(32));"
   ```

   Then update your `.env` file with the generated secret:
   ```
   APP_SECRET=your_generated_secret_here
   ```

   For more details, see [APP_SECRET_README.md](APP_SECRET_README.md).

6. Configure Email Functionality (Optional):

   Install required packages:
   ```bash
   composer require symfony/mailer symfony/monolog-bundle symfony/dependency-injection symfony/mime
   ```

   Configure SMTP in your `.env` file:
   ```
   MAILER_DSN=smtp://user:pass@smtp.example.com:port
   ```

   For more details, see [INSTALL_NOTES.md](INSTALL_NOTES.md).

7. Start the Symfony development server:
   ```bash
   symfony server:start
   ```

## CORS Configuration

CORS (Cross-Origin Resource Sharing) is now configured using a custom event listener that adds the necessary headers to all API responses. This allows the admin and client modules to communicate with the API from different origins.

### Implementation Details

CORS support is implemented using a custom event listener (`CorsListener`) that adds the necessary CORS headers to all API responses. The listener also handles preflight OPTIONS requests that browsers send before making actual cross-origin requests.

### Headers Added

The following CORS headers are added to all responses:

- `Access-Control-Allow-Origin: *` - Allows requests from any origin
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS` - Allows these HTTP methods
- `Access-Control-Allow-Headers: Content-Type, Authorization` - Allows these request headers
- `Access-Control-Max-Age: 3600` - Caches preflight response for 1 hour (only for OPTIONS requests)

### Testing

A test case has been added to verify that the CORS headers are being added correctly:

```bash
cd api
php bin/phpunit tests/Controller/CorsTest.php
```

### Troubleshooting

If you're still experiencing CORS issues:

1. Make sure the API server is running
2. Check that the ReactJS app is using the correct API URL
3. Verify that the browser isn't caching previous responses without CORS headers
4. Check the browser's developer console for specific CORS error messages

For production, you may want to restrict the allowed origins to your specific domains by modifying the `Access-Control-Allow-Origin` header in the `CorsListener` class.

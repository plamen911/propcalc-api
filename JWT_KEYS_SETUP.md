# JWT Keys Setup Guide

## Issue: "Unable to create a signed JWT from the given configuration"

This error occurs when trying to use the `/api/v1/auth/anonymous` endpoint because the JWT keys required for signing tokens are missing. The JWT authentication bundle needs these keys to create signed JWT tokens.

## Solution

Follow these steps to generate the JWT keys:

### 1. Make the script executable

```bash
# Navigate to the API directory
cd api

# Make the script executable
chmod +x generate_jwt_keys.sh
```

### 2. Run the script to generate the keys

```bash
# Run the script
./generate_jwt_keys.sh
```

The script will:
1. Create the `config/jwt` directory if it doesn't exist
2. Generate a private key (`private.pem`) and a public key (`public.pem`)
3. Use the passphrase from your `.env` file (`estate_calculator_jwt_passphrase`)
4. Set appropriate permissions for the keys

### 3. Verify the keys were created

```bash
# Check if the keys exist
ls -la config/jwt
```

You should see two files:
- `private.pem` - The private key used to sign tokens
- `public.pem` - The public key used to verify tokens

## Explanation

The error occurred because:

1. The JWT authentication system requires a pair of keys (private and public) to sign and verify tokens
2. These keys should be located in the `config/jwt` directory
3. The configuration in `.env` points to these keys, but they hadn't been generated yet
4. When the application tried to create a JWT token, it couldn't find the keys needed for signing

Running the `generate_jwt_keys.sh` script creates these keys with the correct passphrase, resolving the error.

## Additional Information

- The JWT passphrase is defined in your `.env` file as `JWT_PASSPHRASE=estate_calculator_jwt_passphrase`
- The keys are configured in `config/packages/lexik_jwt_authentication.yaml`
- For security reasons, the JWT keys are excluded from version control (listed in `.gitignore`)
- In a production environment, you should use a more secure passphrase and ensure the private key is properly protected

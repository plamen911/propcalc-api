#!/bin/bash

# Script to generate JWT keys for the Lexik JWT Authentication Bundle

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Generating JWT keys for authentication...${NC}"

# Create the JWT directory if it doesn't exist
if [ ! -d "config/jwt" ]; then
  echo -e "${GREEN}Creating config/jwt directory...${NC}"
  mkdir -p config/jwt
fi

# Check if the keys already exist
if [ -f "config/jwt/private.pem" ] || [ -f "config/jwt/public.pem" ]; then
  echo -e "${RED}Warning: JWT keys already exist!${NC}"
  read -p "Do you want to overwrite them? (y/n) " -n 1 -r
  echo
  if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Operation cancelled.${NC}"
    exit 1
  fi
fi

# Get the passphrase from .env file or ask the user
if grep -q "JWT_PASSPHRASE" .env; then
  PASSPHRASE=$(grep "JWT_PASSPHRASE" .env | cut -d '=' -f2)
  echo -e "${GREEN}Using passphrase from .env file.${NC}"
else
  echo -e "${YELLOW}JWT_PASSPHRASE not found in .env file.${NC}"
  read -p "Enter a passphrase for the JWT keys: " PASSPHRASE

  # Add the passphrase to .env file
  echo -e "\n###> lexik/jwt-authentication-bundle ###" >> .env
  echo "JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem" >> .env
  echo "JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem" >> .env
  echo "JWT_PASSPHRASE=${PASSPHRASE}" >> .env
  echo "###< lexik/jwt-authentication-bundle ###" >> .env

  echo -e "${GREEN}Added JWT configuration to .env file.${NC}"
fi

# Generate the private key
echo -e "${GREEN}Generating private key...${NC}"
openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:${PASSPHRASE} 4096

# Generate the public key
echo -e "${GREEN}Generating public key...${NC}"
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:${PASSPHRASE}

# Set appropriate permissions
echo -e "${GREEN}Setting file permissions...${NC}"
chmod 644 config/jwt/public.pem
chmod 600 config/jwt/private.pem

echo -e "${GREEN}JWT keys generated successfully!${NC}"
echo -e "${YELLOW}Remember to keep your private key and passphrase secure.${NC}"
echo -e "Private key: config/jwt/private.pem"
echo -e "Public key: config/jwt/public.pem"
echo -e "Passphrase: ${PASSPHRASE}"

echo -e "\n${GREEN}Next steps:${NC}"
echo -e "1. Run migrations: php bin/console doctrine:migrations:migrate"
echo -e "2. Create a user: php bin/console app:create-user admin@example.com password123 Admin User ROLE_ADMIN"
echo -e "3. Test authentication: curl -X POST -H \"Content-Type: application/json\" https://localhost:8000/api/login_check -d '{\"username\":\"admin@example.com\",\"password\":\"password123\"}'"

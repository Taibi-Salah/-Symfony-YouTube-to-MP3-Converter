#!/bin/bash

# Run docker-compose up --build
docker compose up --build -d

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check if gnome-terminal is installed, if not install it
if ! command_exists gnome-terminal; then
    echo "gnome-terminal is not installed. Installing it..."
    sudo apt-get update
    sudo apt-get install -y gnome-terminal
fi

# Check if setup.sh is executable
if [ ! -x ./setup.sh ]; then
    echo "Making setup.sh executable..."
    chmod +x ./setup.sh
fi

# Run setup.sh in a new gnome-terminal
gnome-terminal -- bash -c "./setup.sh; exec bash"

echo "All commands executed successfully!"

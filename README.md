# solaredge_monitor

A PHP script to monitor your SolarEdge solar power system and alert you to issues.

## Features

- Connects to the SolarEdge API to retrieve system data
- Monitors key metrics such as power production and inverter status
- Sends alerts (email, etc.) if issues or anomalies are detected
- Configurable thresholds and alert settings

## Requirements

- PHP 7.0 or higher
- cURL extension enabled
- SolarEdge API key

## Usage

1. Clone this repository:
    ```sh
    git clone https://github.com/yourusername/solaredge_monitor.git
    ```
2. Copy `config.example.php` to `config.php` and update with your SolarEdge API key and site ID.
3. Run the script:
    ```sh
    php solaredge_monitor.php
    ```

## Configuration

Edit `solaredge_monitor.php` to set your API credentials and alert preferences.

## License

MIT License

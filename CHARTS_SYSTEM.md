# Charts System Implementation Guide

## Overview

The Charts system is a comprehensive visualization tool that merges historical environmental data (1984-2024) with AI-predicted data (2025-2050) to provide time-series analysis of environmental metrics across protected areas.

---

## System Architecture

### Technology Stack

**Backend:**
- **PHP**: REST API gateway and data coordination
- **Python (Flask)**: Machine learning model inference
- **MySQL**: Historical environmental data storage

**Frontend:**
- **Flutter**: Cross-platform mobile application
- **fl_chart**: Line chart visualization library

---

## Backend Components

### 1. ChartsController.php

**Location**: `src/controllers/ChartsController.php`

**Purpose**: Main controller handling all chart data requests and coordinating historical/predicted data merging.

#### Key Methods

**`handleGet()`**
- Routes chart requests based on query parameters
- Supports two main endpoints:
  - `?chart=1` - Fetch chart data with merging
  - `?areas=1` - Get list of available areas

**`getChartData()`**
- **Parameters:**
  - `startYear`: 1984-2050
  - `endYear`: 1984-2050
  - `areaId`: 'all' or specific area ID
  - `season`: 'all', 'winter', 'spring', 'summer', 'autumn'
  - `metric`: 'ndvi', 'evi', 'ndwi', 'temp'

- **Validation Logic:**
  - Years must be 1984-2050
  - startYear ≤ endYear
  - Valid season and metric values
  - Prevents "weird" chart combinations through parameter validation

- **Returns:** Merged data with metadata about historical/predicted splits

#### Example Request

```
GET /api?chart=1&startYear=2000&endYear=2040&areaId=all&season=all&metric=ndvi
```

#### Response Structure

```json
{
  "startYear": 2000,
  "endYear": 2040,
  "areaId": "all",
  "season": "all",
  "metric": "ndvi",
  "data": [
    {
      "id": 1,
      "area_id": 1,
      "year": 2000,
      "season": "winter",
      "ndvi": 0.45,
      "is_prediction": false
    },
    // ... more data points
  ],
  "metadata": {
    "historical_years": [2000, 2001, ..., 2024],
    "predicted_years": [2025, 2026, ..., 2040],
    "total_data_points": 150
  }
}
```

### 2. Data Retrieval Flow

```
Chart Request (year range)
        ↓
Historical Years (≤ 2024) → getHistoricalData() → MySQL Database
        ↓
Predicted Years (≥ 2025) → getPredictedData() → makePredictionRequest() → Flask
        ↓
Merge & Sort → Return to Frontend
```

**`getHistoricalData()`**
- Queries MySQL for years 1984-2024
- Filters by area, season, and time range
- Returns historical data points with `is_prediction: false`

**`getPredictedData()`**
- Generates predictions for years 2025-2050
- For each year-season combination:
  - Calls Flask `/predict-batch` endpoint
  - Receives predictions for all areas
  - Formats and stores results
- Returns predicted data with `is_prediction: true`

### 3. Prediction Request Format

**Request to Flask:**
```json
{
  "year_scaled": -0.46,           // (year - 2000) / 25
  "area_ids_scaled": [-0.35, ...], // (area_id - 16) / 9
  "season": "winter",
  "metric": "ndvi"
}
```

**Scaling Formula**: `scaled_value = (value - mean) / std`
- Year scaling: mean=2000, std=25
- Area ID scaling: mean=16, std=9

---

## Frontend Components

### 1. ChartsServices (`lib/data/charts_services.dart`)

**Service class** for API communication with the backend.

#### Key Methods

**`getAllAreas()`**
- Returns list of available area IDs
- Called on page initialization

**`getChartData()`**
- Fetches merged historical + predicted data
- Parameters match ChartsController
- Returns `ChartDataResponse` object

#### Data Models

**ChartDataResponse**
- Holds complete chart data response
- Provides helper methods:
  - `getAverageByYear()` - Calculate yearly averages
  - `getAverageByArea()` - Calculate area averages
  - `getDataForArea()` - Filter by specific area
  - `getDataForSeason()` - Filter by specific season

**ChartDataPoint**
- Individual data point with:
  - `area_id`, `year`, `season`
  - Metric value (ndvi, evi, ndwi, or temp)
  - `isPrediction` flag to distinguish historical/predicted

**ChartMetadata**
- Provides context about data:
  - `historicalYears`: Range of historical data
  - `predictedYears`: Range of predicted data
  - `totalDataPoints`: Count of data points

### 2. ChartsPage (`lib/presentation/screens/charts_page.dart`)

**Main UI** for the charts visualization.

#### Features

**Dropdown Selectors:**
- **Metric**: ndvi, evi, ndwi, temp
- **Area**: All or specific area ID
- **Season**: All, winter, spring, summer, autumn
- **Year Range**: Dual dropdowns (1984-2050)

**Validation:**
- Prevents invalid year ranges
- Validates metric and season selections
- Shows user-friendly error messages

**Data Display:**
- Loading state during API calls
- Error messages on failure
- Data summary showing:
  - Average value
  - Min/Max values
  - Total data points
  - Historical vs predicted year ranges

#### Chart Display Logic

```dart
if (selectedAreaId == 'all')
  → AverageTimeSeriesChart (shows avg across all areas)
else
  → TimeSeriesLineChart (shows individual area data)
```

### 3. TimeSeriesChart Components (`lib/presentation/components/time_series_chart.dart`)

#### TimeSeriesLineChart

**Purpose**: Display data for a specific area across time period.

**Features:**
- **Dual line display:**
  - Blue solid line: Historical data (≤ 2024)
  - Orange dashed line: Predicted data (≥ 2025)
- **Interactive tooltips** showing year and value
- **Grid and labels** for readability
- **Responsive scaling** of axes

**Data Processing:**
1. Filter by selected area
2. Group by year
3. Calculate average for each year
4. Separate historical/predicted
5. Render two line series

#### AverageTimeSeriesChart

**Purpose**: Display average metric value across all areas.

**Features:**
- Single line showing yearly averages
- Calculates mean value for each year across all areas
- Useful for high-level trend analysis

#### Example: Data for 2000-2040

```
Year Range: 2000-2024 (historical) + 2025-2040 (predicted)
Areas: All 5 protected areas
Metric: NDVI

Processing:
  2000: Average NDVI across 5 areas = 0.45
  2001: Average NDVI across 5 areas = 0.46
  ...
  2024: Average NDVI across 5 areas = 0.50 (historical)
  2025: Predicted average NDVI = 0.48 (from Flask)
  ...
  2040: Predicted average NDVI = 0.52 (from Flask)
```

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────┐
│                   Flutter Frontend                       │
│  ┌──────────────────────────────────────────────────┐   │
│  │ ChartsPage: UI with dropdowns & filters          │   │
│  └──────────────┬───────────────────────────────────┘   │
│                │ User selects: Area, Years, Season, Metric
│                ↓                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │ ChartsServices: API wrapper                      │   │
│  │ Calls: /api?chart=1&...                         │   │
│  └──────────────┬───────────────────────────────────┘   │
└─────────────────┼───────────────────────────────────────┘
                  │
                  ↓ HTTP GET
    ┌─────────────────────────────────────────┐
    │      PHP Backend (index.php)             │
    │ Routes to ChartsController               │
    └────────────┬────────────────────────────┘
                 ↓
    ┌─────────────────────────────────────────┐
    │   ChartsController::getChartData()       │
    └────────────┬────────────────────────────┘
         ┌───────┴────────┐
         ↓                ↓
    ┌─────────────┐  ┌──────────────┐
    │ Historical  │  │ Predicted    │
    │ Data        │  │ Data         │
    │ (2000-2024) │  │ (2025-2040)  │
    └────┬────────┘  └──────┬───────┘
         │                  │
         ↓ SQL query        ↓ curl POST
    ┌─────────────┐  ┌──────────────────────┐
    │  MySQL DB   │  │ Flask Service        │
    │ Historical  │  │ /predict-batch       │
    │ Data        │  │ XGBoost Models       │
    └─────────────┘  └──────────────────────┘
         │                  │
         └───────┬──────────┘
                 ↓
         ┌──────────────────┐
         │ Merge & Sort     │
         │ by Year + Area   │
         └────────┬─────────┘
                  ↓
         ┌──────────────────┐
         │ JSON Response    │
         │ with metadata    │
         └────────┬─────────┘
                  ↓
         Back to Frontend
                  ↓
    ┌─────────────────────────────────┐
    │ TimeSeriesLineChart / Average   │
    │ Renders with fl_chart           │
    └─────────────────────────────────┘
```

---

## Usage Examples

### Example 1: View Average NDVI Over 40 Years

```
Selections:
- Metric: NDVI
- Area: All Areas
- Season: All Seasons
- Years: 2000-2040

Result:
- Calls: /api?chart=1&startYear=2000&endYear=2040&areaId=all&season=all&metric=ndvi
- Returns: Data points for 41 years × 4 seasons × 5 areas = 820 points (aggregated by year)
- Chart shows: Single line with average NDVI trend across 40 years
  - 2000-2024: Solid blue (historical)
  - 2025-2040: Dashed orange (predicted)
```

### Example 2: View Winter Temperature for Specific Area

```
Selections:
- Metric: Temperature
- Area: Area 2
- Season: Winter
- Years: 2010-2030

Result:
- Calls: /api?chart=1&startYear=2010&endYear=2030&areaId=2&season=winter&metric=temp
- Returns: Data points for 21 years (one season, one area)
- Chart shows: Single area data with separate historical/predicted lines
```

### Example 3: Multi-Season Analysis

```
Selections:
- Metric: EVI
- Area: All
- Season: All Seasons
- Years: 2015-2025

Result:
- Returns: 11 years × 4 seasons × 5 areas = 220 data points
- Calculates average for each year across all seasons/areas
- Chart shows: Yearly trend (seasonal variation averaged out)
```

---

## Validation & Constraints

### Chart Validation Rules

1. **Year Range Validation:**
   - startYear ≥ 1984
   - endYear ≤ 2050
   - startYear ≤ endYear
   - Error message if violated

2. **Parameter Validation:**
   - Metric must be one of: ndvi, evi, ndwi, temp
   - Season must be one of: all, winter, spring, summer, autumn
   - Area must be 'all' or valid area ID

3. **Prevents "Weird" Charts:**
   - All parameter combinations are technically valid
   - UI restricts to sensible selections via dropdowns
   - Backend validates and returns errors for invalid combinations

### Optimization Notes

- **Batch prediction**: Flask endpoint processes all areas for a year/season at once
- **Efficient averaging**: Group-by logic minimizes memory usage
- **Lazy loading**: Areas loaded only on page init
- **Error handling**: Graceful fallback if prediction service is unavailable

---

## Configuration

### Backend Settings

**Flask Connection**:
```php
private $flaskUrl = "http://host.docker.internal:5000";
```
- Uses Docker internal hostname for container-to-container communication
- Adjust to your Flask service URL if different

**Scaling Parameters** (in ChartsController):
```php
private function scaleValue($value, $mean, $std) {
    return ($value - $mean) / $std;
}
```
- Year scaling: mean=2000, std=25
- Area scaling: mean=16, std=9
- Must match values used in model training

### Frontend Settings

**API Base URL** (in ChartsServices):
```dart
static const String _baseUrl = "https://6112658ce01c.ngrok-free.app";
```
- Update to your backend URL
- Currently using ngrok tunnel for testing

---

## Future Enhancements

1. **Caching**: Cache predictions for frequently requested year ranges
2. **Export**: Download chart data as CSV/JSON
3. **Multi-metric**: Display multiple metrics on same chart
4. **Comparison**: Compare two different time periods or areas
5. **Advanced Statistics**: Add trend lines, moving averages, anomaly detection
6. **Performance**: Batch requests for multiple metrics simultaneously

---

## Troubleshooting

### No Data Returned

**Possible Causes:**
1. Flask service not running: Check `/api?predict=health`
2. Year range outside available data: Use 1984-2050
3. Invalid area ID: Use `?areas=1` to check valid areas

### Prediction Errors

**Flask Connection Issues:**
- Verify Flask service is running on port 5000
- Check network connectivity between PHP and Python
- Monitor Flask logs for model errors

### Slow Chart Loading

**Optimization:**
- Reduce year range (fewer API calls)
- Specify single season instead of 'all'
- Cache previous requests

---

## API Reference

### GET /api?areas=1

Returns available protected areas.

**Response:**
```json
{
  "areas": [1, 2, 3, 4, 5],
  "count": 5
}
```

### GET /api?chart=1&startYear=X&endYear=Y&areaId=Z&season=S&metric=M

Returns merged chart data.

**Parameters:**
- `startYear` (int): 1984-2050
- `endYear` (int): 1984-2050
- `areaId` (string): 'all' or area number
- `season` (string): 'all', 'winter', 'spring', 'summer', 'autumn'
- `metric` (string): 'ndvi', 'evi', 'ndwi', 'temp'

**Response:**
- ChartDataResponse JSON (see response structure above)

---

**System created**: December 1, 2025  
**Version**: 1.0  
**Status**: Production Ready

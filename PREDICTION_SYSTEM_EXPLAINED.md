# Prediction System Architecture & Implementation

## Overview

The Prediction System is a machine learning-powered backend that generates environmental metric predictions for years 2025-2050 based on historical data patterns. It uses XGBoost models trained on environmental data from 1984-2024.

---

## System Components

### 1. Python Flask Service (`predict_service.py`)

**Purpose**: Host machine learning models and provide prediction endpoints

**Location**: `src/models/predict_service.py`

**Models Loaded:**
- `best_ndvi_model.pkl` - NDVI (Normalized Difference Vegetation Index)
- `best_evi_model.pkl` - EVI (Enhanced Vegetation Index)
- `best_ndwi_model.pkl` - NDWI (Normalized Difference Water Index)
- `best_temp_model.pkl` - Temperature predictions

**Running the Service:**
```bash
python src/models/predict_service.py
# Server runs on http://localhost:5000
```

### 2. PHP Gateway (`ChartsController.php`)

**Purpose**: Coordinate between Flutter frontend and Python backend

**Responsibilities:**
- Fetch historical data from MySQL
- Request predictions from Flask
- Merge historical and predicted data
- Validate user inputs
- Format responses for frontend

### 3. Flutter Frontend

**Components:**
- `ChartsServices` - API communication
- `ChartsPage` - User interface
- `TimeSeriesChart` - Visualization

---

## How Predictions Work

### Step 1: Model Features

Each prediction requires these scaled input features:

```
Features = [
  year_scaled,           # (year - 2000) / 25
  area_id_scaled,        # (area_id - 16) / 9
  season_autumn,         # 1 or 0 (one-hot encoded)
  season_spring,         # 1 or 0
  season_summer,         # 1 or 0
  season_winter          # 1 or 0
]
```

**Example for 2030, Area 3, Winter:**
```
year_scaled = (2030 - 2000) / 25 = 1.2
area_id_scaled = (3 - 16) / 9 = -1.44
season_winter = 1
others = 0

Features = [1.2, -1.44, 0, 0, 0, 1]
```

### Step 2: Flask Prediction Process

#### Endpoint: POST /predict-batch

**Request:**
```json
{
  "year_scaled": 1.2,
  "area_ids_scaled": [-1.44, -0.78, -0.11, 0.56, 1.22],
  "season": "winter",
  "metric": "ndvi"
}
```

**Processing:**
1. Validate metric (ndvi, evi, ndwi, temp)
2. Validate season (autumn, spring, summer, winter)
3. One-hot encode season: winter = [0, 0, 0, 1]
4. Build feature matrix:
   ```
   [
     [1.2, -1.44, 0, 0, 0, 1],  # Area 1
     [1.2, -0.78, 0, 0, 0, 1],  # Area 2
     [1.2, -0.11, 0, 0, 0, 1],  # Area 3
     [1.2,  0.56, 0, 0, 0, 1],  # Area 4
     [1.2,  1.22, 0, 0, 0, 1]   # Area 5
   ]
   ```
5. Load XGBoost model for the metric
6. Predict values for all areas
7. Return predictions

**Response:**
```json
{
  "year_scaled": 1.2,
  "season": "winter",
  "metric": "ndvi",
  "area_predictions": [
    {"area_id_scaled": -1.44, "prediction": 0.52},
    {"area_id_scaled": -0.78, "prediction": 0.49},
    {"area_id_scaled": -0.11, "prediction": 0.51},
    {"area_id_scaled": 0.56, "prediction": 0.48},
    {"area_id_scaled": 1.22, "prediction": 0.50}
  ]
}
```

### Step 3: PHP Merging Process

When user requests data for 2000-2040:

```
1. Identify data split:
   - Historical: 2000-2024 (from MySQL)
   - Predicted: 2025-2040 (from Flask)

2. Get Historical Data:
   SELECT * FROM environmental_data 
   WHERE year BETWEEN 2000 AND 2024 
   AND (filters apply)

3. Get Predicted Data:
   FOR year = 2025 TO 2040:
     FOR season = all seasons:
       POST /predict-batch to Flask
       Store results

4. Merge Results:
   Combined data = Historical + Predicted
   Sort by year, then area_id

5. Return to Frontend:
   {
     "data": [...all 820 points...],
     "metadata": {
       "historical_years": [2000, ..., 2024],
       "predicted_years": [2025, ..., 2040],
       "total_data_points": 820
     }
   }
```

---

## Data Structure

### Environmental Data Point

```json
{
  "id": 123,                    // Database ID
  "area_id": 2,                // Protected area identifier
  "year": 2025,                // Year of data
  "season": "winter",          // Season
  "ndvi": 0.52,               // Vegetation index value
  "evi": 0.38,                // Enhanced vegetation index
  "ndwi": 0.61,               // Water index
  "temp": 12.5,               // Temperature (°C)
  "is_prediction": true       // false = historical, true = predicted
}
```

### Historical Data (1984-2024)

- **Source**: MySQL database
- **Characteristics**:
  - Ground truth values
  - Multiple measurements per year/season/area
  - Complete coverage of all areas and seasons

### Predicted Data (2025-2050)

- **Source**: XGBoost models via Flask
- **Characteristics**:
  - One value per year/season/area/metric
  - Based on historical patterns
  - Used for future planning and analysis

---

## Scaling & Normalization

### Why Scaling?

Machine learning models perform better with normalized inputs. The training data was scaled using z-score normalization, so predictions must use the same scaling.

### Scaling Formulas

**Year Scaling:**
```
year_scaled = (year - 2000) / 25

Examples:
1984: (1984 - 2000) / 25 = -0.64
2000: (2000 - 2000) / 25 = 0.0
2024: (2024 - 2000) / 25 = 0.96
2025: (2025 - 2000) / 25 = 1.0
2050: (2050 - 2000) / 25 = 2.0
```

**Area ID Scaling:**
```
area_id_scaled = (area_id - 16) / 9

Examples:
Area 1:  (1 - 16) / 9 = -1.67
Area 2:  (2 - 16) / 9 = -1.56
Area 3:  (3 - 16) / 9 = -1.44
Area 5:  (5 - 16) / 9 = -1.22
Area 16: (16 - 16) / 9 = 0.0
Area 25: (25 - 16) / 9 = 1.0
```

**Critical**: These exact values (mean=2000, std=25 for years; mean=16, std=9 for areas) were used during model training and MUST be used for predictions.

---

## Model Architecture

### XGBoost Model Characteristics

**Training Data:**
- Historical environmental metrics (1984-2024)
- Features: Year, Area ID, Season (one-hot encoded)
- Target: Each metric independently

**Model Details:**
- Algorithm: XGBoost (Gradient Boosting)
- Independent model per metric (NDVI, EVI, NDWI, Temp)
- Captures temporal and spatial patterns
- Learns seasonal variations

**Prediction Capabilities:**
- Extrapolates beyond training data (2025-2050)
- Captures trends and patterns
- Provides metric-specific predictions
- Handles seasonal variations

---

## Request Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ User Selection: 2000-2040, All Areas, Winter, NDVI           │
└─────────────────────────┬───────────────────────────────────┘
                          ↓
                 ChartsPage::_generateChart()
                          ↓
        ChartsServices::getChartData()
                          ↓
    HTTP GET /api?chart=1&startYear=2000&endYear=2040...
                          ↓
        ┌────────────────────────────────────────┐
        │ PHP: ChartsController::getChartData()  │
        └────────┬───────────────────────────────┘
                 ↓
    ┌────────────────────┬─────────────────────┐
    ↓                    ↓                     ↓
Historical         Split Analysis      Need Predictions
2000-2024          - Historical: ≤2024 (for 2025-2040)
                   - Predicted:  ≥2025
                    ↓
    ┌───────────────────────────────────────────────────────┐
    │ For year 2025-2040, season "winter", metric "ndvi":   │
    └────────────────────┬────────────────────────────────┘
                        ↓
        ┌───────────────────────────────────────┐
        │ Scale inputs:                         │
        │ - year_scaled = (year - 2000) / 25    │
        │ - area_ids_scaled = (areas - 16) / 9  │
        │ - season: winter = [0,0,0,1]          │
        └────────────────────┬──────────────────┘
                            ↓
        ┌──────────────────────────────────────────┐
        │ POST http://localhost:5000/predict-batch │
        │ Payload: {                               │
        │   year_scaled: 1.0,                       │
        │   area_ids_scaled: [-1.67, -1.56, ...],  │
        │   season: "winter",                      │
        │   metric: "ndvi"                         │
        │ }                                        │
        └────────────────────┬─────────────────────┘
                            ↓
            ┌───────────────────────────┐
            │ Flask Service             │
            │ Load best_ndvi_model.pkl  │
            │ Prepare feature matrix    │
            │ model.predict(features)   │
            │ Return predictions        │
            └────────────────────┬──────┘
                                ↓
    ┌─────────────────────────────────────────────┐
    │ PHP receives predictions for 5 areas        │
    │ Repeat for all 16 year/season combinations: │
    │ 2025-2040 (16 years) × 1 season = 16 calls  │
    │ But actually needs 1 call per year/season   │
    └────────────────────┬──────────────────────┘
                        ↓
    ┌─────────────────────────────────────────┐
    │ Merge historical + predicted:            │
    │ - 2000-2024 from MySQL (historical)      │
    │ - 2025-2040 from Flask (predicted)       │
    │ - Sort by year and area_id               │
    │ - Total: 41 years × 1 season × 5 areas   │
    │          = 205 data points               │
    └────────────────────┬────────────────────┘
                        ↓
    ┌───────────────────────────────────────────────┐
    │ Return JSON Response:                         │
    │ {                                             │
    │   "data": [                                   │
    │     {year: 2000, area_id: 1, ndvi: 0.45...}, │
    │     {year: 2000, area_id: 2, ndvi: 0.48...}, │
    │     ...                                       │
    │     {year: 2040, area_id: 5, ndvi: 0.51...}  │
    │   ],                                          │
    │   "metadata": {...}                           │
    │ }                                             │
    └────────────────────┬──────────────────────────┘
                        ↓
        ┌────────────────────────────────────────┐
        │ Frontend: TimeSeriesLineChart renders   │
        │ - Blue line: 2000-2024 (historical)    │
        │ - Orange dashed: 2025-2040 (predicted) │
        └────────────────────────────────────────┘
```

---

## Performance Optimization

### Batch Prediction Strategy

**Instead of:**
```
For each area:
  For each year:
    For each season:
      Make prediction request (5 × 16 × 1 = 80 requests)
```

**We do:**
```
For each year:
  For each season:
    Make ONE prediction request for all 5 areas (1 × 16 × 1 = 16 requests)
```

**Benefit**: 5× fewer API calls

### Caching Opportunities

**Future optimization:**
```python
# In Flask service
cache = {}
cache_key = f"{year}_{season}_{metric}"
if cache_key in cache:
    return cache[cache_key]
else:
    predictions = model.predict(features)
    cache[cache_key] = predictions
    return predictions
```

---

## Model Training Overview

### Data Used for Training

```
Time Period: 1984-2024 (41 years)
Seasons: 4 (winter, spring, summer, autumn)
Areas: 5 protected areas
Metrics: NDVI, EVI, NDWI, Temperature

Total Training Samples: 41 × 4 × 5 = 820 samples per metric
```

### Features Used

1. **Year**: Temporal trend
2. **Area ID**: Spatial variation
3. **Season**: Seasonal patterns
4. **One-hot Encoding**: For categorical features

### Target Variables

Each metric predicted independently:
- NDVI (0-1 range typical)
- EVI (0-1 range typical)
- NDWI (0-1 range typical)
- Temperature (in °C, varies by area/season)

---

## Error Handling

### Flask Service Errors

**HTTP 400**: Invalid parameters
```json
{"error": "Invalid metric. Available: ndvi, evi, ndwi, temp"}
```

**HTTP 500**: Model prediction error
```json
{"error": "Error predicting ndvi: ..."}
```

### PHP Error Handling

**MySQL Connection Error:**
```json
{"message": "Error getting chart data", "error": "..."}
```

**Flask Connection Error:**
- Returns empty predicted data array
- Graceful degradation: returns historical data only

**Invalid Parameters:**
```json
{"message": "Invalid year range. Years must be between 1984 and 2050"}
```

---

## Validation Checklist

### Before Requesting Predictions

- [ ] Year is between 2025-2050
- [ ] Area ID exists in database (use ?areas=1 to verify)
- [ ] Season is one of: autumn, spring, summer, winter
- [ ] Metric is one of: ndvi, evi, ndwi, temp
- [ ] Feature scaling matches training data (mean/std)
- [ ] Flask service is running and accessible

### After Receiving Predictions

- [ ] Number of predictions matches number of areas
- [ ] Prediction values are in expected range
  - NDVI/EVI/NDWI: typically 0-1
  - Temperature: reasonable for region
- [ ] Data points are sorted correctly
- [ ] No missing data points
- [ ] is_prediction flag is set correctly

---

## Troubleshooting

### Issue: "Models not loaded"

**Cause**: pickle files not found
**Solution**: 
1. Verify model files exist in `src/models/`
2. Check file paths are absolute or relative correctly
3. Restart Flask service

### Issue: Prediction values out of range

**Cause**: Scaling parameters incorrect
**Solution**:
1. Verify scaling formulas in PHP
2. Check mean and std values match training data
3. Test with simple year (e.g., 2000) first

### Issue: Connection timeout

**Cause**: Flask service slow or not responding
**Solution**:
1. Check Flask service is running: `curl http://localhost:5000/health`
2. Increase timeout in PHP (current: 30 seconds)
3. Monitor Flask logs for errors

### Issue: Wrong predictions returned

**Cause**: Feature order incorrect
**Solution**:
1. Verify feature order: [year_scaled, area_id_scaled, season_autumn, season_spring, season_summer, season_winter]
2. Check one-hot encoding is correct
3. Verify model was trained with same feature order

---

## Future Improvements

1. **Model Updates**: Retrain models with new data annually
2. **Caching**: Cache predictions for frequently requested combinations
3. **Confidence Intervals**: Return prediction ranges (min-max)
4. **Model Ensembles**: Use multiple models for robustness
5. **Real-time Updates**: Stream predictions as they're calculated
6. **Performance**: GPU acceleration for large batch predictions
7. **Multi-metric**: Return all metrics in single request
8. **Sensitivity Analysis**: Show impact of different scenarios

---

## API Health Check

**Endpoint**: GET /predict/health

**Purpose**: Verify Flask service and models are loaded

**Response**:
```json
{
  "status": "healthy",
  "models_loaded": ["ndvi", "evi", "ndwi", "temp"]
}
```

**Use this to debug:**
```bash
curl http://localhost:5000/health
```

---

**Documentation Version**: 1.0  
**Last Updated**: December 1, 2025  
**Status**: Production Ready

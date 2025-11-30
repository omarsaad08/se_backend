# Multi-Metric Prediction System Documentation

## Overview
This system provides machine learning-based predictions for environmental metrics (NDVI, EVI, NDWI, Temperature) across all protected areas for a given year and season.

## Architecture

### Backend Stack
- **Flask Service** (`src/models/predict_service.py`) - Python ML model serving
- **PHP API Gateway** (`src/controllers/PredictionController.php`) - REST API interface
- **Models** - XGBoost models trained on historical data
  - `best_ndvi_model.pkl` - NDVI predictions
  - `best_evi_model.pkl` - EVI predictions
  - `best_ndwi_model.pkl` - NDWI predictions
  - `best_temp_model.pkl` - Temperature predictions

### Frontend Stack
- **Flutter App** (`lib/presentation/screens/home.dart`)
- **Service Layer** (`lib/data/ndvi_services.dart`)
- **Dynamic UI** - Prediction buttons and legend layers

## Flask Prediction Service

### Running the Service
```bash
python src/models/predict_service.py
```
The service runs on `http://localhost:5000`

### Endpoints

#### 1. Single Metric Prediction
**Endpoint**: `POST /predict/<metric>`

**Metrics**: `ndvi`, `evi`, `ndwi`, `temp`

**Request Body**:
```json
{
  "features": [scaled_year, scaled_area_id, season_autumn, season_spring, season_summer, season_winter]
}
```

**Example** (Winter 2024, Area 5):
```bash
curl -X POST http://localhost:5000/predict/ndvi \
  -H "Content-Type: application/json" \
  -d "{\"features\": [-0.46, -0.35, 0, 0, 0, 1]}"
```

**Response**:
```json
{
  "metric": "ndvi",
  "prediction": 0.3421,
  "status": "success"
}
```

#### 2. Batch Predictions (All Areas)
**Endpoint**: `POST /predict-batch`

**Request Body**:
```json
{
  "year_scaled": float,
  "area_ids_scaled": [float, float, ...],
  "season": "autumn|spring|summer|winter"
}
```

**Response**:
```json
{
  "year_scaled": -0.46,
  "season": "winter",
  "area_predictions": [
    {
      "area_id_scaled": -0.35,
      "predictions": {
        "ndvi": 0.3421,
        "evi": 0.2156,
        "ndwi": -0.1234,
        "temp": 18.5
      }
    },
    ...
  ]
}
```

#### 3. Health Check
**Endpoint**: `GET /health`

**Response**:
```json
{
  "status": "healthy",
  "models_loaded": ["ndvi", "evi", "ndwi", "temp"]
}
```

## PHP API Gateway

### Routes

#### Batch Predictions API
**URL**: `/api/predict?batch=1&year=2024&season=winter`

**HTTP Method**: `GET`

**Response**:
```json
{
  "year": 2024,
  "season": "winter",
  "data": {
    "1": {
      "ndvi": 0.3421,
      "evi": 0.2156,
      "ndwi": -0.1234,
      "temp": 18.5
    },
    "2": { ... },
    ...
  }
}
```

### Integration Points
1. **HTTP Client**: Uses `curl` to communicate with Flask service
2. **Data Mapping**: Converts area IDs between scaled and original values
3. **Error Handling**: Catches Flask errors and returns meaningful messages
4. **Scaler Configuration**: Currently uses approximate mean/std values:
   - Year: mean=2000, std=25
   - Area ID: mean=16, std=9
   - **TODO**: Load actual scaler from training pipeline

## Frontend Integration

### Dart Service Methods

#### 1. Get Predictions
```dart
Future<Map<int, Map<String, double>>?> getPredictions(
  int year,
  String season,
) async
```

**Returns**: Map of area_id -> { metric: value }

### Flutter UI Components

#### 1. Prediction Buttons
- Located below feature buttons
- Four buttons: NDVI Pred, EVI Pred, NDWI Pred, Temp Pred
- Purple accent color
- Shows loading spinner when fetching

#### 2. Map Coloring
- When a prediction button is active, map displays predicted values
- Uses same color palette as actual values
- Dynamic legend shows prediction ranges

#### 3. Legend Display
- Automatically switches to prediction legend when prediction button active
- Shows metric icon and "Prediction" suffix
- Same value ranges as actual data

## Feature Comparison

### Historical Data vs Predictions
| Aspect | Historical Data | Predictions |
|--------|-----------------|-------------|
| Source | Database | ML Models |
| Button Color | Native (Green/Blue/Red) | Outlined variant |
| Legend Title | "NDVI - Vegetation Index" | "NDVI Prediction" |
| Icon | Metric-specific | `Icons.auto_graph` |
| Map Rendering | Solid colors | Same palette |

## Workflow

### User Journey
1. Select Year and Season using sliders
2. Click any of 4 prediction buttons (NDVI/EVI/NDWI/Temp Pred)
3. Wait for predictions to load
4. Map highlights with predicted values
5. Legend updates to show prediction ranges
6. Click different prediction button to switch metrics
7. Click feature buttons (NDVI/EVI/NDWI/Temp) to compare with historical data

### Data Flow
```
Frontend (Flutter)
  ↓ (getPredictions request)
NdviServices.dart
  ↓ (HTTP GET /api/predict?batch=...)
PHP Controller
  ↓ (extracts year/season, scales values)
Flask Service
  ↓ (loads model, predicts)
  ↑ (returns predictions for all areas)
PHP Controller
  ↓ (maps to original area IDs)
Frontend
  ↓ (stores in _ndviPredictions/_eviPredictions/etc)
  ↓ (updates map with colors)
```

## Configuration

### Scaling Values (PHP)
Location: `PredictionController::scaleValue()`

Current values (approximate):
```php
$yearScaled = $this->scaleValue($year, 2000, 25);
$areaIdsScaled = array_map(...$this->scaleValue($id, 16, 9)...);
```

**Important**: These should be replaced with actual scaler values from training:
```python
# In Python training code
print(f"Year mean: {scaler.mean_[0]}, std: {scaler.scale_[0]}")
print(f"Area ID mean: {scaler.mean_[1]}, std: {scaler.scale_[1]}")
```

### ngrok URL (Flutter)
Location: `NdviServices::_baseUrl`
```dart
static const String _baseUrl = "https://aa2335d91376.ngrok-free.app";
```
Update this to your current ngrok tunnel URL.

## Error Handling

### Common Errors

#### 1. Flask Service Not Running
**Error**: Connection refused on port 5000
**Solution**: Start Flask service: `python src/models/predict_service.py`

#### 2. Invalid Scaling Values
**Error**: Wildly incorrect predictions
**Solution**: Update scaler values in `PredictionController`

#### 3. Model File Missing
**Error**: FileNotFoundError for .pkl files
**Solution**: Ensure all 4 model files exist in `src/models/`

#### 4. ngrok Connection Failed
**Error**: Frontend doesn't reach predictions endpoint
**Solution**: Update ngrok URL in `NdviServices._baseUrl`

## Testing

### Test with curl
```bash
# Test Flask health
curl http://localhost:5000/health

# Test single prediction
curl -X POST http://localhost:5000/predict/ndvi \
  -H "Content-Type: application/json" \
  -d '{"features": [-0.46, -0.35, 0, 0, 0, 1]}'

# Test batch (via PHP)
curl "http://localhost:8000/api/predict?batch=1&year=2024&season=winter" \
  -H "ngrok-skip-browser-warning: true"
```

## Future Improvements

1. **Load Actual Scaler**: Store StandardScaler object and load in PHP
2. **Confidence Intervals**: Return prediction uncertainty
3. **Time Series**: Support multi-year predictions
4. **Seasonal Trends**: Show seasonal variations in predictions
5. **Model Performance Metrics**: Display R² or RMSE on UI
6. **Caching**: Cache predictions for common year/season combinations
7. **Background Jobs**: Process batch predictions asynchronously
8. **Model Retraining**: Automatic retraining with new data

## Files Modified

### Backend
- `src/models/predict_service.py` - Flask service (created)
- `src/controllers/PredictionController.php` - API gateway (created)
- `src/index.php` - Router updated for prediction paths

### Frontend
- `lib/data/ndvi_services.dart` - Added `getPredictions()` method
- `lib/presentation/screens/home.dart` - Added prediction UI, state, and logic

## Performance Considerations

- **Model Load Time**: ~2-3 seconds on first request
- **Prediction Time**: ~50-100ms per area (32 areas = ~1.5-3 seconds total)
- **API Response**: Usually 2-4 seconds end-to-end
- **Optimization**: Consider caching predictions in database after first request

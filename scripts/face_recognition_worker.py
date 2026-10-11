import sys
import warnings
warnings.filterwarnings("ignore")
import json

try:
    import face_recognition
except Exception as exc:  # pragma: no cover - runtime dependency check
    print(json.dumps({
        'ok': False,
        'error': 'Face recognition Python dependency is not installed. Install `face_recognition` and `numpy` before enrolling or verifying faces.'
    }))
    sys.exit(1)

if len(sys.argv) < 2:
    print(json.dumps({
        'ok': False,
        'error': 'No image path was supplied.'
    }))
    sys.exit(1)

image_path = sys.argv[1]

try:
    image = face_recognition.load_image_file(image_path)
    locations = face_recognition.face_locations(image)

    if len(locations) == 0:
        print(json.dumps({
            'ok': False,
            'error': 'No face was detected in the submitted image.'
        }))
        sys.exit(1)

    if len(locations) > 1:
        print(json.dumps({
            'ok': False,
            'error': 'Multiple faces were detected. Please provide an image with exactly one face.'
        }))
        sys.exit(1)

    encodings = face_recognition.face_encodings(image, known_face_locations=locations, num_jitters=1)

    if not encodings:
        print(json.dumps({
            'ok': False,
            'error': 'The submitted face image is too low quality or invalid.'
        }))
        sys.exit(1)

    print(json.dumps({
        'ok': True,
        'embedding': [float(value) for value in encodings[0]],
        'faces_detected': len(locations),
        'model_version': 'dlib_face_recognition_v1',
    }))
except Exception as exc:  # pragma: no cover - runtime library errors
    print(json.dumps({
        'ok': False,
        'error': f'Face recognition failed: {exc}'
    }))
    sys.exit(1)

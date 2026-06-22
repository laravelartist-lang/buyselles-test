@php($firebaseWeb = config('firebase.web'))
<script>
    window.__FIREBASE_CONFIG__ = @json($firebaseWeb);
</script>
<script type="module" src="{{ mix('js/firebase-backend.js') }}"></script>

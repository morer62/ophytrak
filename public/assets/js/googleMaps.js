function initMap() {
    console.log('initMap called - Google Maps loaded');
    const elements = document.querySelectorAll("[data-places]");
    console.log('Found elements with data-places:', elements.length);
    
    elements.forEach(elm => {
        if (elm.dataset.googleAutocompleteReady === '1') return;
        elm.dataset.googleAutocompleteReady = '1';
        console.log('Processing element:', elm.id, elm);
        const autocomplete = new google.maps.places.Autocomplete(elm);
        
        // Add listener to capture coordinates when a place is selected
        autocomplete.addListener("place_changed", function () {
            console.log('Place changed event triggered for:', elm.id);
            const place = autocomplete.getPlace();
            console.log('Place object:', place);
            
            if (place.geometry) {
                // Try to find corresponding lat/lng input fields
                let latField, lngField;
                
                // Handle different ID patterns
                if (elm.id === 'autocomplete-event-address') {
                    // For client request form - no coordinates needed, just address
                    console.log('Client request form - address selected:', place.formatted_address || place.name);
                } else if (elm.id === 'autocomplete-address') {
                    // For venue search form
                    latField = document.getElementById('lat');
                    lngField = document.getElementById('lng');
                    if (latField) latField.value = place.geometry.location.lat();
                    if (lngField) lngField.value = place.geometry.location.lng();
                    console.log('Venue search form - coordinates set');
                } else {
                    // Generic fallback
                    latField = document.getElementById(elm.id.replace('address', 'lat').replace('autocomplete-', ''));
                    lngField = document.getElementById(elm.id.replace('address', 'lng').replace('autocomplete-', ''));
                    if (latField) latField.value = place.geometry.location.lat();
                    if (lngField) lngField.value = place.geometry.location.lng();
                }
                
                // Update the original input field with the selected address
                elm.value = place.formatted_address || place.name;
                
                console.log('Address set to:', elm.value);
            } else {
                console.log('No geometry found in place object');
            }
        });
    });

    document.querySelectorAll('[data-address-autocomplete]').forEach(input => {
        if (input.dataset.googleAutocompleteReady === '1') return;
        input.dataset.googleAutocompleteReady = '1';
        const form = input.closest('form') || document;
        const autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ['address_components', 'formatted_address', 'geometry', 'place_id'],
            types: ['address']
        });
        const clearSelection = () => {
            const placeId = form.querySelector('[data-address-place-id]');
            if (placeId) placeId.value = '';
            form.querySelectorAll('[data-address-part]').forEach(field => field.value = '');
            const latitude = form.querySelector('[data-address-latitude]');
            const longitude = form.querySelector('[data-address-longitude]');
            if (latitude) latitude.value = '';
            if (longitude) longitude.value = '';
            form.dispatchEvent(new CustomEvent('ophyra:address-cleared'));
        };
        input.addEventListener('input', clearSelection);
        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();
            if (!place || !place.address_components || !place.place_id) {
                clearSelection();
                return;
            }
            const parts = { city: '', state: '', zip: '', country: '' };
            const cityCandidates = { locality: '', postalTown: '', administrativeArea2: '', sublocality: '' };
            place.address_components.forEach(component => {
                const types = component.types || [];
                if (types.includes('locality')) cityCandidates.locality = component.long_name;
                if (types.includes('postal_town')) cityCandidates.postalTown = component.long_name;
                if (types.includes('administrative_area_level_2')) cityCandidates.administrativeArea2 = component.long_name;
                if (types.includes('sublocality_level_1')) cityCandidates.sublocality = component.long_name;
                if (types.includes('administrative_area_level_1')) parts.state = component.short_name;
                if (types.includes('postal_code')) parts.zip = component.long_name;
                if (types.includes('postal_code_suffix')) parts.zip += (parts.zip ? '-' : '') + component.long_name;
                if (types.includes('country')) parts.country = component.short_name;
            });
            parts.city = cityCandidates.locality || cityCandidates.postalTown || cityCandidates.administrativeArea2 || cityCandidates.sublocality;
            input.value = place.formatted_address || input.value;
            Object.entries(parts).forEach(([key, value]) => {
                const target = form.querySelector(`[data-address-part="${key}"]`);
                if (target) target.value = value;
            });
            const placeId = form.querySelector('[data-address-place-id]');
            const latitude = form.querySelector('[data-address-latitude]');
            const longitude = form.querySelector('[data-address-longitude]');
            if (placeId) placeId.value = place.place_id;
            if (latitude && place.geometry?.location) latitude.value = place.geometry.location.lat();
            if (longitude && place.geometry?.location) longitude.value = place.geometry.location.lng();
            form.dispatchEvent(new CustomEvent('ophyra:address-selected'));
            form.querySelector('[name="shipping_address_2"]')?.focus();
        });
    });
}

(function connectDeferredAddressFields() {
    let attempts = 0;
    const connect = function () {
        attempts += 1;
        const pending = document.querySelector('[data-address-autocomplete]:not([data-google-autocomplete-ready="1"])');
        if (pending && window.google && google.maps && google.maps.places) initMap();
        if (attempts < 40 && document.querySelector('[data-address-autocomplete]:not([data-google-autocomplete-ready="1"])')) {
            window.setTimeout(connect, 250);
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', connect, { once: true });
    else connect();
})();

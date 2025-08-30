/**
 * Selector de ubicación geográfica con mapa interactivo
 * 
 * Permite al usuario seleccionar la ubicación de su negocio/sitio web
 * mediante un mapa interactivo usando Leaflet + OpenStreetMap.
 *
 * @package MRI
 * @since 3.6.0
 */

(function($) {
    'use strict';
    
    let map = null;
    let marker = null;
    let geocodingTimeout = null;
    
    const MRIGeoSelector = {
        
        /**
         * Inicializar el selector geográfico
         */
        init: function() {
            this.bindEvents();
            this.initMap();
            this.loadSavedLocation();
        },
        
        /**
         * Enlazar eventos
         */
        bindEvents: function() {
            // Búsqueda de direcciones
            $('#mri-address-search').on('input', this.debounceAddressSearch.bind(this));
            $('#mri-search-address-btn').on('click', this.searchAddress.bind(this));
            
            // Usar ubicación actual
            $('#mri-use-current-location').on('click', this.useCurrentLocation.bind(this));
            
            // Limpiar ubicación
            $('#mri-clear-location').on('click', this.clearLocation.bind(this));
            
            // Auto-completar formulario cuando se cambian coordenadas
            $('#mri_business_latitude, #mri_business_longitude').on('change', this.onCoordinatesChange.bind(this));
        },
        
        /**
         * Inicializar el mapa
         */
        initMap: function() {
            // Cargar Leaflet CSS y JS si no están cargados
            if (typeof L === 'undefined') {
                this.loadLeaflet(() => {
                    this.createMap();
                });
            } else {
                this.createMap();
            }
        },
        
        /**
         * Cargar librerías de Leaflet
         */
        loadLeaflet: function(callback) {
            // Cargar CSS
            if (!$('link[href*="leaflet"]').length) {
                $('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">').appendTo('head');
            }
            
            // Cargar JS
            if (!window.L) {
                $.getScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', callback);
            } else {
                callback();
            }
        },
        
        /**
         * Crear el mapa
         */
        createMap: function() {
            const mapContainer = document.getElementById('mri-map');
            if (!mapContainer) return;
            
            // Coordenadas por defecto (Madrid, España)
            const defaultLat = 40.4168;
            const defaultLng = -3.7038;
            
            // Crear mapa
            map = L.map('mri-map', {
                center: [defaultLat, defaultLng],
                zoom: 13,
                scrollWheelZoom: true
            });
            
            // Agregar capa de tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Manejar clics en el mapa
            map.on('click', this.onMapClick.bind(this));
            
            // Ajustar tamaño después de que el contenedor sea visible
            setTimeout(() => {
                map.invalidateSize();
            }, 100);
        },
        
        /**
         * Manejar clics en el mapa
         */
        onMapClick: function(e) {
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);
            
            this.setMarker(lat, lng);
            this.updateCoordinateFields(lat, lng);
            this.reverseGeocode(lat, lng);
        },
        
        /**
         * Establecer marcador en el mapa
         */
        setMarker: function(lat, lng) {
            if (marker) {
                map.removeLayer(marker);
            }
            
            marker = L.marker([lat, lng], {
                draggable: true,
                title: 'Ubicación de tu negocio'
            }).addTo(map);
            
            // Permitir arrastrar el marcador
            marker.on('dragend', (e) => {
                const position = e.target.getLatLng();
                const newLat = position.lat.toFixed(6);
                const newLng = position.lng.toFixed(6);
                
                this.updateCoordinateFields(newLat, newLng);
                this.reverseGeocode(newLat, newLng);
            });
            
            // Centrar mapa en el marcador
            map.setView([lat, lng], Math.max(map.getZoom(), 15));
        },
        
        /**
         * Actualizar campos de coordenadas
         */
        updateCoordinateFields: function(lat, lng) {
            $('#mri_business_latitude').val(lat);
            $('#mri_business_longitude').val(lng);
            
            // Marcar como modificado para guardar
            this.markFieldsAsChanged();
        },
        
        /**
         * Geocodificación inversa
         */
        reverseGeocode: function(lat, lng) {
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`;
            
            // Mostrar indicador de carga
            $('.mri-geocoding-status').html('<span class="spinner is-active"></span> Obteniendo dirección...').show();
            
            $.get(url)
                .done((data) => {
                    if (data && data.address) {
                        this.fillAddressFields(data.address, data.display_name);
                        $('.mri-geocoding-status').html('✓ Dirección encontrada').delay(2000).fadeOut();
                    }
                })
                .fail(() => {
                    $('.mri-geocoding-status').html('⚠ No se pudo obtener la dirección').delay(3000).fadeOut();
                });
        },
        
        /**
         * Rellenar campos de dirección
         */
        fillAddressFields: function(address, displayName) {
            if (address.road && address.house_number) {
                $('#mri_business_address').val(`${address.road} ${address.house_number}`);
            } else if (address.road) {
                $('#mri_business_address').val(address.road);
            } else if (displayName) {
                $('#mri_business_address').val(displayName.split(',')[0]);
            }
            
            if (address.city || address.town || address.village) {
                $('#mri_business_city').val(address.city || address.town || address.village);
            }
            
            if (address.state) {
                $('#mri_business_state').val(address.state);
            }
            
            if (address.country) {
                $('#mri_business_country').val(address.country);
            }
            
            if (address.postcode) {
                $('#mri_business_postal_code').val(address.postcode);
            }
            
            this.markFieldsAsChanged();
        },
        
        /**
         * Búsqueda de dirección con debounce
         */
        debounceAddressSearch: function() {
            clearTimeout(geocodingTimeout);
            geocodingTimeout = setTimeout(() => {
                this.searchAddress();
            }, 1000);
        },
        
        /**
         * Buscar dirección
         */
        searchAddress: function() {
            const query = $('#mri-address-search').val().trim();
            if (!query) return;
            
            $('.mri-search-status').html('<span class="spinner is-active"></span> Buscando...').show();
            
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&addressdetails=1`;
            
            $.get(url)
                .done((data) => {
                    if (data && data.length > 0) {
                        const result = data[0];
                        const lat = parseFloat(result.lat).toFixed(6);
                        const lng = parseFloat(result.lon).toFixed(6);
                        
                        this.setMarker(lat, lng);
                        this.updateCoordinateFields(lat, lng);
                        
                        if (result.address) {
                            this.fillAddressFields(result.address, result.display_name);
                        }
                        
                        $('.mri-search-status').html('✓ Ubicación encontrada').delay(2000).fadeOut();
                    } else {
                        $('.mri-search-status').html('⚠ No se encontró la dirección').delay(3000).fadeOut();
                    }
                })
                .fail(() => {
                    $('.mri-search-status').html('⚠ Error en la búsqueda').delay(3000).fadeOut();
                });
        },
        
        /**
         * Usar ubicación actual del usuario
         */
        useCurrentLocation: function() {
            if (!navigator.geolocation) {
                alert('Tu navegador no soporta geolocalización.');
                return;
            }
            
            $('.mri-location-status').html('<span class="spinner is-active"></span> Obteniendo ubicación...').show();
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude.toFixed(6);
                    const lng = position.coords.longitude.toFixed(6);
                    
                    this.setMarker(lat, lng);
                    this.updateCoordinateFields(lat, lng);
                    this.reverseGeocode(lat, lng);
                    
                    $('.mri-location-status').html('✓ Ubicación obtenida').delay(2000).fadeOut();
                },
                (error) => {
                    let message = 'No se pudo obtener la ubicación.';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Permiso de ubicación denegado.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Ubicación no disponible.';
                            break;
                        case error.TIMEOUT:
                            message = 'Tiempo de espera agotado.';
                            break;
                    }
                    
                    $('.mri-location-status').html(`⚠ ${message}`).delay(5000).fadeOut();
                }
            );
        },
        
        /**
         * Limpiar ubicación
         */
        clearLocation: function() {
            if (confirm('¿Estás seguro de que quieres limpiar la ubicación?')) {
                // Limpiar marcador
                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }
                
                // Limpiar campos
                $('#mri_business_latitude, #mri_business_longitude, #mri_business_address, #mri_business_city, #mri_business_state, #mri_business_country, #mri_business_postal_code').val('');
                
                this.markFieldsAsChanged();
            }
        },
        
        /**
         * Cargar ubicación guardada
         */
        loadSavedLocation: function() {
            const lat = $('#mri_business_latitude').val();
            const lng = $('#mri_business_longitude').val();
            
            if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                setTimeout(() => {
                    this.setMarker(lat, lng);
                }, 500);
            }
        },
        
        /**
         * Manejar cambios en coordenadas manuales
         */
        onCoordinatesChange: function() {
            const lat = $('#mri_business_latitude').val();
            const lng = $('#mri_business_longitude').val();
            
            if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                this.setMarker(lat, lng);
                this.reverseGeocode(lat, lng);
            }
        },
        
        /**
         * Marcar campos como modificados
         */
        markFieldsAsChanged: function() {
            // Disparar evento de cambio para que WordPress detecte cambios no guardados
            $('#mri_business_latitude, #mri_business_longitude, #mri_business_address, #mri_business_city, #mri_business_state, #mri_business_country, #mri_business_postal_code').trigger('change');
        }
    };
    
    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        // Solo inicializar en la página de configuración del plugin
        if ($('#mri-map').length) {
            MRIGeoSelector.init();
        }
        
        // Manejar cambios de tab
        $('.nav-tab').on('click', function() {
            if ($(this).attr('href') === '#geo-settings' && map) {
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        });
    });
    
})(jQuery);
<template>
    <div class="p-4">
        <!-- Tabs para cambiar entre URL y archivo -->
        <div class="mb-4 border-b">
            <div class="flex gap-4">
                <button 
                    @click="activeTab = 'url'"
                    :class="['px-4 py-2', activeTab === 'url' ? 'border-b-2 border-blue-500' : '']"
                >
                    Analizar URL
                </button>
                <button 
                    @click="activeTab = 'file'"
                    :class="['px-4 py-2', activeTab === 'file' ? 'border-b-2 border-blue-500' : '']"
                >
                    Analizar Archivo
                </button>
            </div>
        </div>

        <!-- Input URL -->
        <div v-if="activeTab === 'url'" class="flex gap-2">
            <input
                v-model="url"
                type="url"
                placeholder="Ingrese URL para analizar"
                class="border px-2 py-1 rounded flex-1"
            />
            <button 
                @click="submitUrl" 
                class="bg-blue-500 text-white px-4 py-1 rounded hover:bg-blue-600"
                :disabled="scanProgress !== null"
            >
                Analizar URL
            </button>
        </div>

        <!-- Input Archivo -->
        <div v-if="activeTab === 'file'" class="flex gap-2">
            <input
                type="file"
                @change="handleFileUpload"
                class="border px-2 py-1 rounded flex-1"
                :disabled="scanProgress !== null"
            />
        </div>

        <!-- Barra de progreso -->
        <div v-if="scanProgress !== null" class="mt-4">
            <div class="flex justify-between text-sm text-gray-600 mb-1">
                <span>Progreso del análisis</span>
                <span>{{ scanProgress }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div 
                    class="bg-blue-600 h-2.5 rounded-full transition-all duration-500"
                    :style="{ width: `${scanProgress}%` }"
                ></div>
            </div>
        </div>

        <!-- Resultados parciales -->
        <div v-if="partialResults" class="mt-4 p-4 bg-gray-50 rounded-lg border">
            <h3 class="font-semibold mb-2">Resultados preliminares:</h3>
            <div class="grid grid-cols-2 gap-4">
                <div v-if="partialResults.stats" class="space-y-2">
                    <p class="text-green-600">
                        Seguros: {{ partialResults.stats.harmless || 0 }}
                    </p>
                    <p class="text-red-600">
                        Maliciosos: {{ partialResults.stats.malicious || 0 }}
                    </p>
                    <p class="text-yellow-600">
                        Sospechosos: {{ partialResults.stats.suspicious || 0 }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Resultado final -->
        <div v-if="result" class="mt-4 p-4 bg-gray-100 rounded">
            <pre class="whitespace-pre-wrap">{{ JSON.stringify(result, null, 2) }}</pre>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import axios from '@/lib/axios'

const url = ref('')
const scanProgress = ref<number | null>(null)
const result = ref<any>(null)
const partialResults = ref<any>(null)
const activeTab = ref('url')

// First, let's define an interface for our API response
interface ScanResponse {
    scanId: string;
    message?: string;
}

// Update the handleFileUpload function
const handleFileUpload = async (e: Event) => {
    // Reset any previous progress/results
    scanProgress.value = null
    result.value = null
    partialResults.value = null

    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    
    // Validate file presence
    if (!file) {
        alert('Por favor seleccione un archivo para analizar')
        return
    }

    // Validate file size (max 32MB for VirusTotal)
    const maxSize = 600 * 1024 * 1024 // 32MB in bytes
    if (file.size > maxSize) {
        alert('El archivo no debe superar los 600MB')
        return
    }

    try {
        const formData = new FormData()
        formData.append('file', file)

        const { data } = await axios.post<ScanResponse>('/api/scan/file', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            },
            onUploadProgress: (progressEvent: { loaded: number; total?: number }) => {
                if (progressEvent.total) {
                    const progress = (progressEvent.loaded / progressEvent.total) * 100
                    scanProgress.value = Math.round(progress)
                } else {
                    // Si total no está disponible, mostrar progreso indeterminado
                    scanProgress.value = 0
                }
            }
        })
        
        if (!data?.scanId) {
            throw new Error('La respuesta del servidor no incluye un ID de análisis válido')
        }

        console.log('Respuesta recibida:', data)
        watchProgress(data.scanId)
    } catch (error: any) {
        console.error('Error detallado:', {
            error,
            response: error.response?.data
        })
        
        if (error.response?.status === 422) {
            const errorMsg = error.response.data.errors?.file?.[0] || 'Error de validación'
            alert(errorMsg)
        } else {
            alert(error.response?.data?.message || 'Error al procesar el archivo')
        }
        
        // Reset states
        scanProgress.value = null
        result.value = null
        partialResults.value = null
        
        // Reset file input
        if (target) {
            target.value = ''
        }
    }
}

// Update the submitUrl function
const submitUrl = async () => {
    scanProgress.value = null
    result.value = null
    partialResults.value = null

    const trimmedUrl = url.value.trim()
    if (!trimmedUrl || !trimmedUrl.match(/^https?:\/\/.+/)) {
        alert('Por favor ingresa una URL válida que empiece con http o https')
        return
    }

    try {
        const { data } = await axios.post<ScanResponse>('/api/scan/url', {
            url: trimmedUrl
        })

        if (!data?.scanId) {
            throw new Error('La respuesta del servidor no incluye un ID de análisis válido')
        }

        console.log('Respuesta recibida:', data)
        watchProgress(data.scanId)
    } catch (error: any) {
        console.error('Error detallado:', {
            error,
            response: error.response?.data,
            url: trimmedUrl
        })
        
        alert(error.response?.data?.message || 'Error al procesar la URL')
        
        // Reset states
        scanProgress.value = null
        url.value = ''
    }
}

const watchProgress = (scanId: string) => {
    const interval = setInterval(async () => {
        try {
            const { data } = await axios.get(`/api/scan/progress/${scanId}`)
            scanProgress.value = data.progress
            partialResults.value = data.partial_results

            if (data.progress >= 100) {
                clearInterval(interval)
                const res = await axios.get(`/api/scan/result/${scanId}`)
                result.value = res.data.result
            }
        } catch (error) {
            console.error('Error al obtener progreso:', error)
            clearInterval(interval)
        }
    }, 1500)
}
</script>

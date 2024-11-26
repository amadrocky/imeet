<template>
    <div class="text-center">
        <video ref="video" @loadedmetadata="initVideo" autoplay muted playsinline></video>
    </div>
</template>

<script>
    import QrCode from 'qrcode-reader';
    import axios from 'axios';
    import Swal from 'sweetalert2';

    export default {
        mounted() {
            this.initCamera();
        },
        props: {
            checkUrl: {
                type: String,
                required: true
            }
        },
        methods: {
            async initCamera() {
                try {
                    // Vérification du support de la caméra
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Votre appareil ne prend pas en charge la capture vidéo.'
                        });
                        return;
                    }

                    // Vérification des permissions
                    const permissions = await navigator.permissions.query({ name: 'camera' });
                    if (permissions.state !== 'granted') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Permission caméra',
                            text: 'Veuillez autoriser l’accès à la caméra pour continuer.'
                        });
                    }

                    // Obtenir l'ID de la caméra arrière
                    let rearCameraId = await this.getRearCamera();

                    if (!rearCameraId) {
                        console.warn('Aucune caméra arrière détectée. Utilisation de la caméra par défaut.');
                        const devices = await navigator.mediaDevices.enumerateDevices();
                        const videoDevices = devices.filter(device => device.kind === 'videoinput');
                        if (videoDevices.length > 0) {
                            rearCameraId = videoDevices[0].deviceId; // Utilisation du premier appareil disponible
                        }
                    }

                    if (!rearCameraId) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur caméra',
                            text: 'Impossible de trouver une caméra fonctionnelle.'
                        });
                        return;
                    }

                    // Activer le flux vidéo
                    const constraints = {
                        video: {
                            deviceId: rearCameraId ? { exact: rearCameraId } : undefined,
                            facingMode: { ideal: "environment" }
                        }
                    };

                    console.log('Contraintes vidéo appliquées :', constraints);

                    const stream = await navigator.mediaDevices.getUserMedia(constraints);
                    const video = this.$refs.video;

                    video.srcObject = stream;
                    video.play();
                } catch (err) {
                    console.error('Erreur lors de l’initialisation de la caméra :', err.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur caméra',
                        text: `Impossible d’activer la caméra : ${err.message}`
                    });
                }
            },

            async getRearCamera() {
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(device => device.kind === 'videoinput');

                    console.log('Caméras détectées :', videoDevices);

                    let rearCamera = videoDevices.find(device =>
                        device.label.toLowerCase().includes('back') ||
                        device.label.toLowerCase().includes('rear')
                    );

                    if (!rearCamera && videoDevices.length > 1) {
                        console.warn('Caméra arrière non détectée, sélection de la seconde caméra.');
                        rearCamera = videoDevices[1];
                    }

                    if (!rearCamera) {
                        console.error('Aucune caméra arrière détectée.');
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur caméra',
                            text: 'Aucune caméra arrière détectée. Essayez de vérifier les permissions ou de redémarrer votre appareil.'
                        });
                        return null;
                    }

                    console.log('Caméra arrière détectée :', rearCamera);
                    return rearCamera.deviceId;
                } catch (err) {
                    console.error('Erreur lors de la détection des caméras :', err.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur caméra',
                        text: 'Une erreur s’est produite lors de la détection des caméras.'
                    });
                    return null;
                }
            },

            initVideo() {
                const video = this.$refs.video;
                const qr = new QrCode();

                qr.callback = (err, value) => {
                    if (err) {
                        console.warn('Erreur de décodage QR:', err.message);
                    } else {
                        this.handleQRCode(value.result);
                    }
                };

                let isDecoding = false;
                const captureFrame = () => {
                    if (isDecoding) {
                        requestAnimationFrame(captureFrame);
                        return;
                    }

                    isDecoding = true;

                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                    try {
                        qr.decode(imageData);
                    } catch (decodeError) {
                        console.warn('Décodage échoué :', decodeError.message);
                    }

                    setTimeout(() => {
                        isDecoding = false;
                    }, 500);

                    requestAnimationFrame(captureFrame);
                };
                requestAnimationFrame(captureFrame);
            },

            async handleQRCode(qrCodeData) {
                try {
                    const response = await axios.post(this.checkUrl, { 'url': qrCodeData });

                    if (response.status === 200) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: response.data.message
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Code invalide',
                            text: 'Le QR code scanné est invalide.'
                        });
                    }
                } catch (error) {
                    console.error('Erreur lors de la validation du QR code :', error.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Impossible de valider le QR code. Réessayez.'
                    });
                }
            }
        }
    };
</script>

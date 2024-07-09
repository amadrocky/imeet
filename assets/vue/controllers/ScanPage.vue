<template>
    <div class="text-center">
        <video ref="video" @loadedmetadata="initVideo"></video>
    </div>
</template>
  
<script>
    import QrCode from 'qrcode-reader';
    import axios from 'axios';
    import Swal from 'sweetalert2'

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
                const video = this.$refs.video;

                const rearCameraId = await getRearCamera();

                if (!rearCameraId) {
                    console.error('No rear camera found');
                    return;
                }

                const constraints = {
                    video: {
                        deviceId: rearCameraId ? { exact: rearCameraId } : undefined,
                        facingMode: { ideal: "environment" }
                    }
                };

                try {
                    const stream = await navigator.mediaDevices.getUserMedia(constraints);

                    if (stream && typeof stream === 'object' && stream instanceof MediaStream) {
                        video.srcObject = stream;
                        video.play();
                    } else {
                        throw new Error('Stream is not a valid MediaStream');
                    }
                } catch (err) {
                    console.error('Failed to initialize camera:', err.name, err.message);
                }
            },
            initVideo() {
                const video = this.$refs.video;
                video.play();

                const qr = new QrCode();
                qr.callback = (err, value) => {
                    if (err) {
                        // nothing
                    } else {
                        this.handleQRCode(value.result);
                    }
                };
                
                const captureFrame = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    qr.decode(imageData);
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
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Code invalide',
                        text: false
                    });
                }
            },
            async getRearCamera() {
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(device => device.kind === 'videoinput');
                    let rearCamera = videoDevices.find(device => device.label.toLowerCase().includes('back'));
                    
                    if (!rearCamera && videoDevices.length > 1) {
                        rearCamera = videoDevices[1];
                    }
                    
                    return rearCamera ? rearCamera.deviceId : null;
                } catch (err) {
                    console.error('Error enumerating devices:', err);
                    return null;
                }
            }
        }
    };
</script>


declare module 'qrcode' {
    type QrCodeOptions = {
        errorCorrectionLevel?: 'L' | 'M' | 'Q' | 'H';
        margin?: number;
        width?: number;
    };

    const QRCode: {
        toDataURL(text: string, options?: QrCodeOptions): Promise<string>;
    };

    export default QRCode;
}

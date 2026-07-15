declare module 'html5-qrcode' {
    export type CameraDevice = {
        id: string;
        label: string;
    };

    export class Html5Qrcode {
        constructor(
            elementId: string,
            configOrVerbose?: boolean | { verbose?: boolean },
        );

        static getCameras(): Promise<CameraDevice[]>;

        start(
            cameraIdOrConfig:
                | string
                | { facingMode: string }
                | { deviceId: { exact: string } },
            configuration: {
                fps?: number;
                qrbox?: number | { width: number; height: number };
                aspectRatio?: number;
            },
            qrCodeSuccessCallback: (decodedText: string, decodedResult: unknown) => void,
            qrCodeErrorCallback?: (errorMessage: string, error: unknown) => void,
        ): Promise<void>;

        stop(): Promise<void>;
        clear(): void;
    }
}
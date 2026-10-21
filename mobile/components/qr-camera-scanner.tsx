import { MaterialCommunityIcons } from '@expo/vector-icons';
import { BarcodeScanningResult, CameraView, useCameraPermissions } from 'expo-camera';
import { useEffect, useState } from 'react';
import { Modal, Pressable, SafeAreaView, StyleSheet, Text, View } from 'react-native';

import { palette } from '@/constants/palette';

function checkInCodeFromQr(value: string) {
  const content = value.trim();

  if (!content) {
    return null;
  }

  try {
    const parsed = new URL(content);
    const code = parsed.searchParams.get('code')?.trim();

    if (code) {
      return code;
    }
  } catch {
    // Plain booking references and check-in codes are valid QR payloads too.
  }

  return /^[a-z0-9-]{4,80}$/i.test(content) ? content : null;
}

export function QrCameraScanner({
  visible,
  onClose,
  onCode,
}: {
  visible: boolean;
  onClose: () => void;
  onCode: (code: string) => void;
}) {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanLocked, setScanLocked] = useState(false);
  const [message, setMessage] = useState('Point the rear camera at the customer event pass.');

  useEffect(() => {
    if (visible) {
      setScanLocked(false);
      setMessage('Point the rear camera at the customer event pass.');
    }
  }, [visible]);

  function handleBarcode({ data }: BarcodeScanningResult) {
    if (scanLocked) {
      return;
    }

    const code = checkInCodeFromQr(data);

    if (!code) {
      setMessage('That QR code is not a valid customer event pass. Try another code.');
      return;
    }

    setScanLocked(true);
    setMessage('Customer pass detected. Checking in...');
    onCode(code);
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <SafeAreaView style={styles.page}>
        <View style={styles.header}>
          <View style={styles.headerCopy}>
            <Text style={styles.eyebrow}>Staff check-in</Text>
            <Text style={styles.title}>Scan customer QR</Text>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel="Close camera" onPress={onClose} style={styles.closeButton}>
            <MaterialCommunityIcons name="close" size={24} color="#FFFFFF" />
          </Pressable>
        </View>

        <View style={styles.cameraShell}>
          {!permission ? (
            <View style={styles.permissionPanel}>
              <Text style={styles.status}>Checking camera permission...</Text>
            </View>
          ) : !permission.granted ? (
            <View style={styles.permissionPanel}>
              <MaterialCommunityIcons name="camera-lock-outline" size={44} color={palette.brandYellow} />
              <Text style={styles.permissionTitle}>Camera access is required</Text>
              <Text style={styles.status}>Allow camera access so staff can scan the customer QR pass.</Text>
              <Pressable onPress={() => void requestPermission()} style={styles.primaryButton}>
                <Text style={styles.primaryButtonText}>Allow camera</Text>
              </Pressable>
            </View>
          ) : (
            <CameraView
              style={styles.camera}
              facing="back"
              barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
              onBarcodeScanned={scanLocked ? undefined : handleBarcode}>
              <View style={styles.overlay}>
                <View style={styles.scanFrame} />
              </View>
            </CameraView>
          )}
        </View>

        <Text style={[styles.status, message.startsWith('That QR') ? styles.errorText : null]}>{message}</Text>
        <Pressable onPress={onClose} style={styles.manualButton}>
          <Text style={styles.manualButtonText}>Use manual code instead</Text>
        </Pressable>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  page: {
    flex: 1,
    backgroundColor: '#17191C',
    paddingHorizontal: 18,
    paddingVertical: 14,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
    marginBottom: 18,
  },
  headerCopy: { flex: 1, gap: 4 },
  eyebrow: {
    color: palette.brandYellow,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  title: { color: '#FFFFFF', fontSize: 27, fontWeight: '900' },
  closeButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 1,
    borderColor: '#50545A',
    alignItems: 'center',
    justifyContent: 'center',
  },
  cameraShell: {
    width: '100%',
    aspectRatio: 3 / 4,
    maxHeight: 560,
    overflow: 'hidden',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#4C5056',
    backgroundColor: '#08090A',
  },
  camera: { flex: 1 },
  overlay: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  scanFrame: {
    width: '72%',
    aspectRatio: 1,
    borderWidth: 3,
    borderColor: palette.brandYellow,
    borderRadius: 8,
    backgroundColor: 'transparent',
  },
  permissionPanel: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 14,
    padding: 24,
  },
  permissionTitle: { color: '#FFFFFF', fontSize: 20, fontWeight: '900', textAlign: 'center' },
  status: { color: '#D5D9DE', fontSize: 14, lineHeight: 21, textAlign: 'center', marginTop: 16 },
  errorText: { color: '#FF9B91' },
  primaryButton: {
    minHeight: 48,
    borderRadius: 8,
    paddingHorizontal: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: palette.brandRed,
  },
  primaryButtonText: { color: '#FFFFFF', fontSize: 15, fontWeight: '900' },
  manualButton: { minHeight: 48, alignItems: 'center', justifyContent: 'center', marginTop: 6 },
  manualButtonText: { color: '#FFFFFF', fontSize: 14, fontWeight: '800' },
});

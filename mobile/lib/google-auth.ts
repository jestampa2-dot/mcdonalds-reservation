import {
  GoogleSignin,
  isErrorWithCode,
  isSuccessResponse,
  statusCodes,
} from '@react-native-google-signin/google-signin';

const webClientId = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID?.trim() ?? '';
let configured = false;

export function isGoogleSignInConfigured() {
  return webClientId.length > 0;
}

function configureGoogleSignIn() {
  if (!webClientId) {
    throw new Error('Google sign-in is not configured in this app build yet.');
  }

  if (!configured) {
    GoogleSignin.configure({
      webClientId,
      offlineAccess: false,
      scopes: ['profile', 'email'],
    });
    configured = true;
  }
}

export async function chooseGoogleAccount(): Promise<string | null> {
  configureGoogleSignIn();

  await GoogleSignin.hasPlayServices({ showPlayServicesUpdateDialog: true });
  const response = await GoogleSignin.signIn();

  if (!isSuccessResponse(response)) {
    return null;
  }

  if (!response.data.idToken) {
    throw new Error('Google did not return a secure ID token. Check the Web OAuth client ID used by this app.');
  }

  return response.data.idToken;
}

export function googleSignInErrorMessage(error: unknown) {
  if (isErrorWithCode(error)) {
    if (error.code === statusCodes.PLAY_SERVICES_NOT_AVAILABLE) {
      return 'Google Play Services is unavailable or out of date on this phone.';
    }

    if (error.code === statusCodes.IN_PROGRESS) {
      return 'Google sign-in is already open. Finish it before trying again.';
    }

    if (error.code === statusCodes.SIGN_IN_CANCELLED) {
      return '';
    }
  }

  return error instanceof Error ? error.message : 'Unable to sign in with Google right now.';
}

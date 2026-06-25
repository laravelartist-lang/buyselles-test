import 'dart:async';
import 'dart:developer';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';

class GoogleSignInController with ChangeNotifier {
  final GoogleSignIn _googleSignIn = GoogleSignIn.instance;
  GoogleSignInAccount? googleAccount;
  GoogleSignInClientAuthorization? auth;
  String? idToken;
  String errorMessage = '';

  GoogleSignInController();

  Future<void> initialize({required String clientId}) async {
    await _googleSignIn.initialize(
      serverClientId: clientId,
    );

    _googleSignIn.authenticationEvents.listen(_handleAuthenticationEvent);
  }

  Future<void> _handleAuthenticationEvent(GoogleSignInAuthenticationEvent event) async {
    googleAccount = switch (event) {
      GoogleSignInAuthenticationEventSignIn() => event.user,
      GoogleSignInAuthenticationEventSignOut() => null,
    };

    if (googleAccount != null) {
      try {
        // Request access token via authorization scopes
        const List<String> scopes = <String>['email', 'profile', 'openid'];
        auth = await googleAccount?.authorizationClient.authorizationForScopes(scopes);

        // Attempt to get the ID token from the authentication object
        final authDetails = googleAccount?.authentication;
        idToken = authDetails?.idToken;

        log('GoogleSignIn: idToken=${idToken != null ? "obtained" : "null"}, accessToken=${auth?.accessToken != null ? "obtained" : "null"}');
      } catch (e) {
        log('GoogleSignIn: Failed to get tokens: $e');
        idToken = null;
      }
    } else {
      auth = null;
      idToken = null;
    }

    notifyListeners();
  }

  Future<void> login() async {
    try {
      errorMessage = '';

      final completer = Completer<void>();

      // Temporary subscription to wait for event
      final sub = _googleSignIn.authenticationEvents.listen((event) async {
        await _handleAuthenticationEvent(event);
        if (!completer.isCompleted) completer.complete();
      });

      await _googleSignIn.authenticate();
      await completer.future.timeout(
        const Duration(seconds: 30),
        onTimeout: () {
          if (!completer.isCompleted) completer.complete();
        },
      );
      await sub.cancel();
    } catch (e) {
      errorMessage = _errorMessageFromSignInException(e);
      notifyListeners();
      rethrow;
    }
  }

  String _errorMessageFromSignInException(dynamic e) {
    if (e is GoogleSignInException) {
      return switch (e.code) {
        GoogleSignInExceptionCode.canceled => 'Sign in canceled',
        _ => 'GoogleSignInException ${e.code}: ${e.description}',
      };
    }
    return 'Unknown error: $e';
  }

  Future<void> logout() async {
    await _googleSignIn.disconnect();
    idToken = null;
    auth = null;
    googleAccount = null;
    notifyListeners();
  }
}
